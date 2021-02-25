<?php

/**
 * @package   Gantry5
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2021 RocketTheme, LLC
 * @license   GNU/GPLv2 and later
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Gantry\Framework;

use Gantry\Admin\ThemeList;
use Gantry\Component\Filesystem\Folder;
use Gantry\Component\Outline\OutlineCollection;
use Gantry\Debugger;
use Gantry\Joomla\StyleHelper;
use Joomla\CMS\Factory;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class Outlines
 * @package Gantry\Framework
 */
class Outlines extends OutlineCollection
{
    /** @var int */
    protected $createId;

    /**
     * @param int|string $id
     * @return int|string
     */
    public function preset($id)
    {
        if (is_numeric($id)) {
            $style = StyleHelper::getStyle($id);
            $params = json_decode($style->params, true);

            $id = isset($params['preset']) ? $params['preset'] : 'default';
        }

        return $id;
    }

    /**
     * @param object|null $template
     * @return mixed
     */
    public function current($template = null)
    {
        $application = Factory::getApplication();

        if (!is_object($template)) {
            // Get the template style.
            $template = $application->getTemplate(true);
        }

        $preset = $template->params->get('preset', 'default');
        $outline = $template->params->get('configuration', !empty($template->id) ? $template->id : null);

        if (GANTRY_DEBUGGER) {
            Debugger::addMessage('Template Style:' . $template);
        }

        if (JDEBUG && !$outline) {
            static $shown = false;

            if (!$shown) {
                $shown = true;
                $application->enqueueMessage('[DEBUG] JApplicationSite::getTemplate() was overridden with no specified Gantry 5 outline.', 'notice');
            }
        }

        /** @var UniformResourceLocator $locator */
        $locator = $this->container['locator'];

        return ($outline && is_dir($locator("{$this->path}/{$outline}"))) ? $outline : $preset;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function load($path = 'gantry-config://')
    {
        $this->path = $path;

        $gantry = $this->container;

        $theme = isset($gantry['theme.name']) ? $gantry['theme.name'] : null;

        $styles = ThemeList::getStyles($theme);

        $installer = new ThemeInstaller($this->container['theme.name']);
        $title = $installer->getStyleName('%s - ');

        $outlines = [];
        foreach ($styles as $style) {
            $preset = isset($style->params['preset']) ? $style->params['preset'] : null;
            $outline = isset($style->params['configuration']) ? $style->params['configuration'] : $preset;

            if ($outline && $outline != $style->id) {
                // New style generated by Joomla.
                StyleHelper::copy($style, $outline, $style->id);
            }
            $outlines[$style->id] = preg_replace('|^' . preg_quote($title, '|') . '|', '', $style->style);
        }

        asort($outlines);

        $this->items = $this->addDefaults($outlines);

        return $this;
    }

    /**
     * @param string|null $id
     * @param string $title
     * @param string|array $preset
     * @return string
     * @throws \RuntimeException
     */
    public function create($id, $title = null, $preset = null)
    {
        if ($this->createId) {
            // Workaround Joomla wanting to use different logic for style duplication.
            $new = parent::create($this->createId, $title, $preset);

            $this->createId = null;

            return $new;
        }

        $title = $title ? "%s - {$title}" : '%s - Untitled';

        $installer = new ThemeInstaller($this->container['theme.name']);
        $title = $installer->getStyleName($title);
        $style = $installer->addStyle($title);

        $error = $style->getError();

        if ($error) {
            throw new \RuntimeException($error, 400);
        }

        $presetId = (string) (isset($preset['preset']['name']) ? $preset['preset']['name'] : ($preset ?: 'default'));

        StyleHelper::update($style->id, $presetId);

        // Create configuration folder.
        $newId = parent::create($style->id, $title, $preset);

        if ($newId != $style->id) {
            throw new \RuntimeException(sprintf("Creating outline: folder '%s' already exists!", $style->id));
        }

        return $style->id;
    }

    /**
     * @param string $id
     * @param null $title
     * @param bool $inherit
     * @return string
     * @throws \RuntimeException
     */
    public function duplicate($id, $title = null, $inherit = false)
    {
        if (!$this->canDuplicate($id)) {
            throw new \RuntimeException("Outline '$id' cannot be duplicated", 400);
        }

        // Handle special case of duplicating system outlines.
        if ((string)(int) $id !== (string) $id) {
            return parent::duplicate($id, $title, $inherit);
        }

        // Use Joomla logic to duplicate the style.
        $model = StyleHelper::loadModel();
        $pks = [$id];

        try {
            $model->duplicate($pks);
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage(), 400, $e);
        }

        // Seek the newly generated style ID since Joomla doesn't return one on duplication.
        $theme = $this->container['theme.name'];
        $styles = ThemeList::getStyles($theme, true);
        $style = end($styles);

        if ($title) {
            // Change the title.
            $installer = new ThemeInstaller($theme);
            $title = $installer->getStyleName("%s - {$title}");
            $this->rename($style->id, $title);
        } else {
            $title = $style->style;
        }

        $this->createId = $style->id;

        return parent::duplicate($id, $title, $inherit);
    }

    /**
     * @param string $id
     * @param string $title
     * @return string
     * @throws \RuntimeException
     */
    public function rename($id, $title)
    {
        $model = StyleHelper::loadModel();

        $item = $model->getTable();
        $item->load($id);

        if (!$item->id) {
            throw new \RuntimeException('Outline not found', 404);
        }

        $theme = $this->container['theme.name'];
        $installer = new ThemeInstaller($theme);

        $title = $title ? "%s - {$title}" : '%s - Untitled';
        $title = $installer->getStyleName($title);

        $item->title = $title;

        if (!$item->check()) {
            throw new \RuntimeException($item->getError(), 400);
        }

        if (!$item->store()) {
            throw new \RuntimeException($item->getError(), 500);
        }

        if (isset($this->items[$id])) {
            $this->items[$id] = $title;
        }

        return $id;
    }

    /**
     * @param string $id
     * @param bool $deleteModel
     * @throws \Exception
     */
    public function delete($id, $deleteModel = true)
    {
        if (!$this->canDelete($id)) {
            throw new \RuntimeException("Outline '$id' cannot be deleted", 400);
        }

        $model = StyleHelper::loadModel();

        $item = $model->getTable();
        $item->load($id);

        try {
            foreach ($this->getInheritingOutlines($id) as $outline => $title) {
                $this->layout($outline)->updateInheritance($id)->save()->saveIndex();
            }
            foreach ($this->getInheritingOutlinesWithAtom($id) as $outline => $title) {
                Atoms::instance($outline)->updateInheritance($id)->save();
            }

            if ($deleteModel && !$model->delete($id)) {
                $error = $model->getError();
                // Well, Joomla can always send enqueue message instead!
                if (!$error) {
                    $messages = Factory::getApplication()->getMessageQueue();
                    $message = reset($messages);
                    $error = $message ? $message['message'] : 'Unknown error';
                }
                throw new \RuntimeException($error);
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Deleting outline failed: ' . $e->getMessage(), 400, $e);
        }

        // Remove configuration directory.
        $gantry = $this->container;

        /** @var UniformResourceLocator $locator */
        $locator = $gantry['locator'];
        $path = $locator->findResource("{$this->path}/{$item->id}", true, true);
        if ($path) {
            if (file_exists($path)) {
                Folder::delete($path);
            }
        }

        unset($this->items[$item->id]);
    }

    /**
     * @param string $id
     * @return boolean
     */
    public function canDelete($id)
    {
        $style = StyleHelper::getStyle($id);

        return !(!$style->id || $style->home);
    }

    /**
     * @param string $id
     * @return boolean
     */
    public function isDefault($id)
    {
        $style = StyleHelper::getStyle($id);

        return (bool) $style->home;
    }
}
