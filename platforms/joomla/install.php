<?php
defined('_JEXEC') or die;

/**
 * Gantry 5 package installer script.
 */
class Pkg_Gantry5InstallerScript
{
    /**
     * List of supported versions. Newest version first!
     * @var array
     */
    protected $versions = array(
        'PHP' => array (
            '5.4' => '5.4.0',
            '0' => '5.5.9' // Preferred version
        ),
        'Joomla!' => array (
            '3.4' => '3.4.0',
            '0' => '3.4.0' // Preferred version
        )
    );
    /**
     * List of required PHP extensions.
     * @var array
     */
    protected $extensions = array ('json', 'pcre');

    public function install($parent)
    {
        return true;
    }

    public function discover_install($parent)
    {
        return self::install($parent);
    }

    public function update($parent)
    {
        return self::install($parent);
    }

    public function uninstall($parent)
    {
        // Hack.. Joomla really doesn't give any information from the extension that's being uninstalled..
        $manifestFile = JPATH_MANIFESTS . '/packages/pkg_gantry5.xml';
        if (is_file($manifestFile)) {
            $manifest = simplexml_load_file($manifestFile);
            $this->prepareExtensions($manifest, 0);
        }

        // Clear cached files.
        if (is_dir(JPATH_CACHE . '/gantry5')) {
            JFolder::delete(JPATH_CACHE . '/gantry5');
        }
        if (is_dir(JPATH_SITE . '/cache/gantry5')) {
            JFolder::delete(JPATH_SITE . '/cache/gantry5');
        }

        return true;
    }

    public function preflight($type, $parent)
    {
        /** @var JInstallerAdapter $parent */
        $manifest = $parent->getManifest();

        // Prevent installation if requirements are not met.
        $errors = $this->checkRequirements($manifest->version);
        if ($errors) {
            $app = JFactory::getApplication();

            foreach ($errors as $error) {
                $app->enqueueMessage($error, 'error');
            }
            return false;
        }

        // Disable and unlock existing extensions to prevent fatal errors (in the site).
        $this->prepareExtensions($manifest, 0);

        return true;
    }

    public function postflight($type, $parent)
    {
        // Clear Joomla system cache.
        /** @var JCache|JCacheController $cache */
        $cache = JFactory::getCache();
        $cache->clean('_system');

        // Remove all compiled files from APC cache.
        if (function_exists('apc_clear_cache')) {
            @apc_clear_cache();
        }

        if ($type == 'uninstall') {
            return true;
        }

        /** @var JInstallerAdapter $parent */
        $manifest = $parent->getManifest();

        // Enable and lock extensions to prevent uninstalling them individually.
        $this->prepareExtensions($manifest, 1);

        return true;
    }

    // Internal functions

    protected function prepareExtensions($manifest, $state = 1)
    {
        foreach ($manifest->files->children() as $file) {
            $attributes = $file->attributes();

            $search = ['type' => (string) $attributes->type, 'element' => (string) $attributes->id];

            $clientName = (string) $attributes->client;
            if (!empty($clientName)) {
                $client = JApplicationHelper::getClientInfo($clientName, true);
                $search +=  ['client_id' => $client->id];
            }

            $group = (string) $attributes->group;
            if (!empty($group)) {
                $search +=  ['folder' => $group];
            }

            $extension = JTable::getInstance('extension');

            if (!$extension->load($search)) {
                continue;
            }

            $extension->protected = $state;
            $extension->enabled = $state;
            $extension->store();
        }
    }

    protected function checkRequirements()
    {
        $results = array();
        $this->checkVersion($results, 'PHP', phpversion());
        $this->checkVersion($results, 'Joomla!', JVERSION);
        $this->checkExtensions($results, $this->extensions);

        return $results;
    }

    protected function checkVersion(array &$results, $name, $version)
    {
        $major = $minor = 0;
        foreach ($this->versions[$name] as $major => $minor) {
            if (!$major || version_compare($version, $major, '<')) {
                continue;
            }

            if (version_compare($version, $minor, '>=')) {
                return;
            }
            break;
        }

        if (!$major) {
            $minor = reset($this->versions[$name]);
        }

        $recommended = end($this->versions[$name]);

        if (version_compare($recommended, $minor, '>')) {
            $results[] = sprintf(
                '%s %s is not supported. Minimum required version is %s %s, but it is highly recommended to use %s %s or later version.',
                $name,
                $version,
                $name,
                $minor,
                $name,
                $recommended
            );
        } else {
            $results[] = sprintf(
                '%s %s is not supported. Please update to %s %s or later version.',
                $name,
                $version,
                $name,
                $minor
            );
        }
    }

    protected function checkExtensions(array &$results, $extensions)
    {
        foreach ($extensions as $name) {
            if (!extension_loaded($name)) {
                $results[] = sprintf("Required PHP extension '%s' is missing. Please install it into your system.", $name);
            }
        }
    }
}
