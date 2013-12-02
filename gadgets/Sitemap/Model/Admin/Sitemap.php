<?php
/**
 * Sitemap Gadget
 *
 * @category   GadgetModel
 * @package    Sitemap
 * @author     Mojtaba Ebrahimi <ebrahimi@zehneziba.ir>
 * @copyright  2013 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/gpl.html
 */
class Sitemap_Model_Admin_Sitemap extends Sitemap_Model_Sitemap
{
    /**
     * Get a gadget category properties
     *
     * @access  public
     * @param   string  $gadget
     * @param   string  $category
     * @return  array   Category data
     */
    function GetCategoryProperties($gadget, $category)
    {
        $sitemapTable = Jaws_ORM::getInstance()->table('sitemap');
        $sitemapTable->select('id:integer', 'gadget', 'category', 'priority:float', 'frequency', 'status');
        $sitemapTable->where('gadget', $gadget);
        return $sitemapTable->and()->where('category', $category)->fetchRow();
    }

    /**
     * Update a category properties
     *
     * @access  public
     * @param   string  $gadget     Gadget name
     * @param   string  $category   Category name
     * @param   array   $data       Sitemap properties
     * @return  mixed   Array of Tag info or Jaws_Error on failure
     */
    function UpdateCategory($gadget, $category, $data)
    {
        // check for exiting category properties in DB
        $categoryProperties = $this->GetCategoryProperties($gadget, $category);
        if(empty($categoryProperties)) {
            // Add new record to DB
            $table = Jaws_ORM::getInstance()->table('sitemap');
            $data['gadget'] = $gadget;
            $data['category'] = $category;
            $result = $table->insert($data)->exec();
        } else {
            // Update exiting record in DB
            $table = Jaws_ORM::getInstance()->table('sitemap');
            $result = $table->update($data)->where('gadget', $gadget)->and()->where('category', $category)->exec();
        }

        return $result;
    }

    /**
     * Update a gadget properties
     *
     * @access  public
     * @param   string  $gadget     Gadget name
     * @param   array   $data       Sitemap properties
     * @return  mixed   Array of Tag info or Jaws_Error on failure
     */
    function UpdateGadget($gadget, $data)
    {
        if($this->gadget->registry->fetch($gadget)==null) {
            return $this->gadget->registry->insert($gadget, serialize($data));
        } else {
            return $this->gadget->registry->update($gadget, serialize($data));
        }
    }

    /**
     * Sync sitemap XML files
     *
     * @access  public
     * @param   string  $gadget  Gadget name
     * @return  mixed   Array of Tag info or Jaws_Error on failure
     */
    function SyncSitemapXML($gadget)
    {
        $tpl = $this->gadget->template->loadAdmin('GadgetXML.html');
        $tpl->SetBlock('xml');

        // Fetch default sitemap config from registry
        $defaultPriority = $this->gadget->registry->fetch('sitemap_default_priority');
        $defaultFrequency = $this->gadget->registry->fetch('sitemap_default_frequency');

        // Fetch gadget sitemap config
        $gadgetProperties = $this->GetGadgetProperties($gadget);
        $gadgetPriority = null;
        $gadgetFrequency = null;
        if (!empty($gadgetProperties)) {
            if(isset($gadgetProperties['priority'])) {
                $gadgetPriority = $gadgetProperties['priority'];
            }
            if(isset($gadgetProperties['frequency'])) {
                $gadgetFrequency = $gadgetProperties['frequency'];
            }
        }

        $frequencyArray = array(
            1 => 'always',
            2 => 'hourly',
            3 => 'daily',
            4 => 'weekly',
            5 => 'monthly',
            6 => 'yearly',
            7 => 'never'
        );

        $objGadget = Jaws_Gadget::getInstance($gadget);
        if (Jaws_Error::IsError($objGadget)) {
            return '';
        }
        $objHook = $objGadget->hook->load('Sitemap');
        if (Jaws_Error::IsError($objHook)) {
            return '';
        }

        $allItems = $objHook->Execute(2);
        if (Jaws_Error::IsError($allItems) || empty($allItems)) {
            return '';
        }

        $allCategories = $objHook->Execute(1);
        $gadgetCategories = $this->GetGadgetCategoryProperties($gadget);
        $finalCategory = array();
        foreach($allCategories as $cat) {
            $property = array();
            if(isset($gadgetCategories[$cat['id']])) {
                $property['priority'] = $gadgetCategories[$cat['id']]['priority'];
                $property['frequency'] = $gadgetCategories[$cat['id']]['frequency'];

            } else {
                $property['priority'] = $defaultPriority;
                $property['frequency'] = $defaultFrequency;
            }
            $finalCategory[$cat['id']] = $property;
        }

        $date = Jaws_Date::getInstance();
        foreach ($allItems as $item) {
            $tpl->SetBlock('xml/item');
            $tpl->SetVariable('loc', $item['url']);
            if(!empty($item['lastmod'])) {
                $tpl->SetBlock('xml/item/lastmod');
                $tpl->SetVariable('lastmod',  $date->ToISO($item['lastmod']));
                $tpl->ParseBlock('xml/item/lastmod');
            }

            // Frequency
            $frequency = null;
            if (!empty($item['parent'])) {
                $frequency = $finalCategory[$item['parent']]['frequency'];
            }
            if (empty($frequency)) {
                $frequency = $gadgetFrequency;
            }
            if (empty($frequency)) {
                $frequency = $defaultFrequency;
            }
            if (!empty($frequency)) {
                $tpl->SetBlock('xml/item/changefreq');
                $tpl->SetVariable('changefreq', $frequencyArray[$frequency]);
                $tpl->ParseBlock('xml/item/changefreq');
            }

            // Priority
            $priority = null;
            if (!empty($item['parent'])) {
                $priority = $finalCategory[$item['parent']]['priority'];
            }
            if ($priority == null) {
                $priority = $gadgetPriority;
            }
            if ($priority == null) {
                $priority = $defaultPriority;
            }

            if ($priority != null) {
                $tpl->SetBlock('xml/item/priority');
                $tpl->SetVariable('priority', $priority);
                $tpl->ParseBlock('xml/item/priority');
            }

            $tpl->ParseBlock('xml/item');
        }

        $tpl->ParseBlock('xml');
        $xmlContent = $tpl->Get();

        // Check gadget directory in sitemap
        $gadget_dir = JAWS_DATA . 'sitemap' . DIRECTORY_SEPARATOR . strtolower($gadget) . DIRECTORY_SEPARATOR;
        if (!Jaws_Utils::mkdir($gadget_dir)) {
            return new Jaws_Error(_t('GLOBAL_ERROR_FAILED_CREATING_DIR', $gadget_dir), _t('SITEMAP_NAME'));
        }

        $cache_file = $gadget_dir . 'sitemap.xml';
        if (!Jaws_Utils::file_put_contents($cache_file, $xmlContent)) {
            return false;
        }

        // remove Main sitemap.xml cached file
        $xml_file = JAWS_DATA . 'sitemap' . DIRECTORY_SEPARATOR . 'sitemap.xml';
        if (file_exists($xml_file)) {
            @unlink($xml_file);
        }

        // Change gadget update time
        $gadgetProperties = $this->GetGadgetProperties($gadget);
        $gadgetProperties['update_time'] = time();
        $this->UpdateGadget($gadget, $gadgetProperties);

        return true;
    }


    /**
     * Sync sitemap data files
     *
     * @access  public
     * @param   string  $gadget  Gadget name
     * @return  mixed   Array of Tag info or Jaws_Error on failure
     */
    function SyncSitemapData($gadget)
    {
        $objGadget = Jaws_Gadget::getInstance($gadget);
        if (Jaws_Error::IsError($objGadget)) {
            return '';
        }
        $objHook = $objGadget->hook->load('Sitemap');
        if (Jaws_Error::IsError($objHook)) {
            return '';
        }

        $result[$gadget] = array();
        $gResult = $objHook->Execute(1);
        if (Jaws_Error::IsError($gResult) || empty($gResult)) {
            return '';
        }

        // Check gadget directory in sitemap
        $gadget_dir = JAWS_DATA . 'sitemap' . DIRECTORY_SEPARATOR . $gadget . DIRECTORY_SEPARATOR;
        if (!Jaws_Utils::mkdir($gadget_dir)) {
            return new Jaws_Error(_t('GLOBAL_ERROR_FAILED_CREATING_DIR', $gadget_dir), _t('SITEMAP_NAME'));
        }

        $cache_file = $gadget_dir . 'sitemap.bin';
        if (!Jaws_Utils::file_put_contents($cache_file, serialize($gResult))) {
            return false;
        }
        return true;
    }
}