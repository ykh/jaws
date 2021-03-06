<?php
/**
 * Directory Gadget
 *
 * @category    Gadget
 * @package     Directory
 * @author      Mohsen Khahani <mkhahani@gmail.com>
 * @copyright   2013-2015 Jaws Development Group
 * @license     http://www.gnu.org/copyleft/gpl.html
 */
class Directory_Actions_Admin_Directory extends Directory_Actions_Admin_Common
{
    /**
     * Builds file management UI
     *
     * @access  public
     * @return  string  XHTML UI
     */
    function Directory()
    {
        $GLOBALS['app']->Layout->AddHeadLink('gadgets/Directory/Resources/style.css');
        $this->AjaxMe('script.js');
        $tpl = $this->gadget->template->loadAdmin('Workspace.html');
        $tpl->SetBlock('workspace');

        $tpl->SetVariable('menubar', $this->MenuBar('Directory'));

        $tpl->SetVariable('lbl_search', _t('GLOBAL_SEARCH'));
        $tpl->SetVariable('lbl_adv_search', _t('DIRECTORY_ADVANCED_SEARCH'));
        $tpl->SetVariable('lbl_new_dir', _t('DIRECTORY_NEW_DIR'));
        $tpl->SetVariable('lbl_new_file', _t('DIRECTORY_NEW_FILE'));
        $tpl->SetVariable('lbl_props', _t('DIRECTORY_PROPERTIES'));
        $tpl->SetVariable('lbl_edit', _t('GLOBAL_EDIT'));
        $tpl->SetVariable('lbl_delete', _t('GLOBAL_DELETE'));
        $tpl->SetVariable('lbl_move', _t('DIRECTORY_MOVE'));
        $tpl->SetVariable('lbl_dl', _t('DIRECTORY_DOWNLOAD'));

        $tpl->SetVariable('img_new_dir', STOCK_DIRECTORY_NEW);
        $tpl->SetVariable('img_new_file', STOCK_NEW);
        $tpl->SetVariable('img_props', 'images/stock/properties.png');
        $tpl->SetVariable('img_edit', STOCK_EDIT);
        $tpl->SetVariable('img_delete', STOCK_DELETE);
        $tpl->SetVariable('img_move', STOCK_RIGHT);
        $tpl->SetVariable('img_dl', STOCK_SAVE);
        $tpl->SetVariable('img_search', STOCK_SEARCH);

        $dir_id = (int)jaws()->request->fetch('dirid');
        $tpl->SetVariable('currentDir', $dir_id);
        $tpl->SetVariable('UID', (int)$GLOBALS['app']->Session->GetAttribute('user'));
        $tpl->SetVariable('home_title', _t('DIRECTORY_HOME'));
        $tpl->SetVariable('home_url', BASE_SCRIPT . '?gadget=Directory');
        $tpl->SetVariable('lbl_title', _t('DIRECTORY_FILE_TITLE'));
        $tpl->SetVariable('lbl_created', _t('DIRECTORY_FILE_CREATED'));
        $tpl->SetVariable('lbl_modified', _t('DIRECTORY_FILE_MODIFIED'));
        $tpl->SetVariable('lbl_term', _t('DIRECTORY_FILE_TERM'));
        $tpl->SetVariable('lbl_tags', _t('DIRECTORY_FILE_TAGS'));
        $tpl->SetVariable('lbl_type', _t('DIRECTORY_FILE_TYPE'));
        $tpl->SetVariable('lbl_size', _t('DIRECTORY_FILE_SIZE'));
        $tpl->SetVariable('lbl_start_date', _t('DIRECTORY_FILE_START_DATE'));
        $tpl->SetVariable('lbl_end_date', _t('DIRECTORY_FILE_END_DATE'));
        $tpl->SetVariable('alertShortQuery', _t('DIRECTORY_ERROR_SHORT_QUERY'));
        $tpl->SetVariable('confirmDelete', _t('DIRECTORY_CONFIRM_DELETE'));
        $tpl->SetVariable('confirmFileDelete', _t('DIRECTORY_CONFIRM_FILE_DELETE'));
        $tpl->SetVariable('imgDeleteFile', STOCK_DELETE);
        $tpl->SetVariable('site_url', $GLOBALS['app']->getSiteURL('/'));
        $theme = $GLOBALS['app']->GetTheme();
        $icon_url = is_dir($theme['url'] . 'mimetypes')?
            $theme['url'] . 'mimetypes/' : 'images/mimetypes/';
        $tpl->SetVariable('icon_url', $icon_url);

        // Start date
        $cal_type = $this->gadget->registry->fetch('calendar', 'Settings');
        $cal_lang = $this->gadget->registry->fetch('site_language', 'Settings');
        $datePicker =& Piwi::CreateWidget('DatePicker', 'start_date');
        $datePicker->showTimePicker(true);
        $datePicker->setCalType($cal_type);
        $datePicker->setLanguageCode($cal_lang);
        $datePicker->setDateFormat('%Y-%m-%d');
        $datePicker->setStyle('width:80px');
        $tpl->SetVariable('start_date', $datePicker->Get());

        // End date
        $datePicker =& Piwi::CreateWidget('DatePicker', 'end_date');
        $datePicker->showTimePicker(true);
        $datePicker->setDateFormat('%Y-%m-%d');
        $datePicker->SetIncludeCSS(false);
        $datePicker->SetIncludeJS(false);
        $datePicker->setCalType($cal_type);
        $datePicker->setLanguageCode($cal_lang);
        $datePicker->setStyle('width:80px');
        $tpl->SetVariable('end_date', $datePicker->Get());

        // File template
        $tpl->SetBlock('workspace/fileTemplate');
        $tpl->SetVariable('id', '{id}');
        $tpl->SetVariable('url', '{url}');
        $tpl->SetVariable('title', '{title}');
        $tpl->SetVariable('description', '{description}');
        $tpl->SetVariable('icon', '{icon}');
        $tpl->SetVariable('type', '{type}');
        $tpl->SetVariable('size', '{size}');
        $tpl->SetVariable('created', '{created}');
        $tpl->SetVariable('modified', '{modified}');
        $tpl->ParseBlock('workspace/fileTemplate');

        $tpl->ParseBlock('workspace');
        return $tpl->Get();
    }

    /**
     * Fetches list of files
     *
     * @access  public
     * @return  array   File data or an empty array
     */
    function GetFiles()
    {
        $data = jaws()->request->fetch(array('parent'));
        $user = (int)$GLOBALS['app']->Session->GetAttribute('user');
        $model = $this->gadget->model->loadAdmin('Files');
        $files = $model->GetFiles($data);
        if (Jaws_Error::IsError($files)){
            return array();
        }
        $objDate = Jaws_Date::getInstance();
        foreach ($files as &$file) {
            if ($file['is_dir']) {
                $file['url'] = BASE_SCRIPT . '?gadget=Directory&action=Directory&dirid=' . $file['id'];
            } else {
                $file['link'] = $this->gadget->urlMap('Directory', array('id' => $file['id']), true);
            }
            $file['created'] = $objDate->Format($file['createtime'], 'n/j/Y g:i a');
            $file['modified'] = $objDate->Format($file['updatetime'], 'n/j/Y g:i a');

            // Fetch tags
            $file['tags'] = array();
            if (Jaws_Gadget::IsGadgetInstalled('Tags')) {
                $tModel = Jaws_Gadget::getInstance('Tags')->model->loadAdmin('Tags');
                $tags = $tModel->GetReferenceTags('Directory', 'file', $file['id']);
                $file['tags'] = implode(', ', array_filter($tags));
            }
        }

        return $files;
    }

    /**
     * Fetches data of a file/directory
     *
     * @access  public
     * @return  array   File data or an empty array
     */
    function GetFile()
    {
        $id = jaws()->request->fetch('id', 'post');
        $model = $this->gadget->model->loadAdmin('Files');
        $file = $model->GetFile($id);
        if (Jaws_Error::IsError($file)) {
            return array();
        }
        $objDate = Jaws_Date::getInstance();
        $file['created'] = $objDate->Format($file['createtime'], 'n/j/Y g:i a');
        $file['modified'] = $objDate->Format($file['updatetime'], 'n/j/Y g:i a');

        return $file;
    }

    /**
     * Fetches path of a file/directory
     *
     * @access  public
     * @return  array   Directory hierarchy
     */
    function GetPath()
    {
        $id = jaws()->request->fetch('id');
        $path = array();
        $model = $this->gadget->model->loadAdmin('Files');
        $model->GetPath($id, $path);
        return $path;
    }

    /**
     * Builds a (sub)tree of directories
     *
     * @access  public
     * @return  string   XHTML tree
     */
    function GetTree()
    {
        $tree = '';
        $exclude = jaws()->request->fetch('id_set');
        $exclude = empty($exclude)? array() : explode(',', $exclude);
        $this->BuildTree(0, $exclude, $tree);

        $tpl = $this->gadget->template->loadAdmin('Move.html');
        $tpl->SetBlock('tree');
        $tpl->SetVariable('lbl_ok', _t('GLOBAL_OK'));
        $tpl->SetVariable('lbl_cancel', _t('GLOBAL_CANCEL'));
        $tpl->SetVariable('tree', $tree);
        $tpl->ParseBlock('tree');
        return $tpl->Get();
    }

    /**
     * Builds a (sub)tree of directories
     *
     * @access  public
     * @param   int     $root       File ID as tree root
     * @param   array   $exclude    Set of IDs to be excluded in tree
     * @param   string  $tree       XHTML tree
     * @return  void
     */
    function BuildTree($root = 0, $exclude = array(), &$tree)
    {
        $model = $this->gadget->model->loadAdmin('Files');
        $user = (int)$GLOBALS['app']->Session->GetAttribute('user');
        $dirs = $model->GetFiles(array('parent' => $root, 'is_dir' => true));
        if (Jaws_Error::IsError($dirs)) {
            return;
        }
        if (!empty($dirs)) {
            $tree .= '<ul>';
            foreach ($dirs as $dir) {
                if (in_array($dir['id'], $exclude)) {
                    continue;
                }
                $tree .= "<li><a id='node_{$dir['id']}'>{$dir['title']}</a>";
                $this->BuildTree($dir['id'], $exclude, $tree);
                $tree .= "</li>";
            }
            $tree .= '</ul>';
        }
    }

    /**
     * Deletes passed file(s)/directorie(s)
     *
     * @access  public
     * @return  mixed   Response array
     */
    function Delete()
    {
        $id_set = jaws()->request->fetch('id_set');
        $id_set = explode(',', $id_set);
        if (empty($id_set)) {
            return $GLOBALS['app']->Session->GetResponse(
                _t('DIRECTORY_ERROR_DELETE'),
                RESPONSE_ERROR
            );
        }

        $model = $this->gadget->model->loadAdmin('Files');
        $user = (int)$GLOBALS['app']->Session->GetAttribute('user');
        $fault = false;
        foreach ($id_set as $id) {
            // Validate file
            $file = $model->GetFile($id);
            if (Jaws_Error::IsError($file)) {
                $fault = true;
                continue;
            }

            // Delete file/directory
            $res = $model->Delete($file);
            if (Jaws_Error::IsError($res)) {
                $fault = true;
            }

            // Delete tags
            if (Jaws_Gadget::IsGadgetInstalled('Tags')) {
                $tModel = Jaws_Gadget::getInstance('Tags')->model->loadAdmin('Tags');
                $tModel->DeleteReferenceTags('Directory', 'file', $id);
            }
        }

        if ($fault === true) {
            return $GLOBALS['app']->Session->GetResponse(
                _t('DIRECTORY_WARNING_DELETE'),
                RESPONSE_WARNING
            );
        } else {
            return $GLOBALS['app']->Session->GetResponse(
                _t('DIRECTORY_NOTICE_ITEMS_DELETED'),
                RESPONSE_NOTICE
            );
        }
    }

    /**
     * Moves file/directory to the given target directory
     *
     * @access  public
     * @return  array   Response array
     */
    function Move()
    {
        $data = jaws()->request->fetch(array('id_set', 'target'));
        if (empty($data['id_set']) || is_null($data['target'])) {
            return $GLOBALS['app']->Session->GetResponse(
                _t('DIRECTORY_ERROR_MOVE'),
                RESPONSE_ERROR
            );
        }

        $id_set = explode(',', $data['id_set']);
        $target = (int)$data['target'];
        $model = $this->gadget->model->loadAdmin('Files');

        // Validate target
        if ($target !== 0) {
            $dir = $model->GetFile($target);
            if (Jaws_Error::IsError($dir) || !$dir['is_dir']) {
                return $GLOBALS['app']->Session->GetResponse(
                    _t('DIRECTORY_ERROR_MOVE'),
                    RESPONSE_ERROR
                );
            }
        }

        $fault = false;
        foreach ($id_set as $id) {
            // Prevent moving to itself
            if ($target == $id) {
                $fault = true;
                continue;
            }

            // Validate file
            $file = $model->GetFile($id);
            if (Jaws_Error::IsError($file)) {
                $fault = true;
                continue;
            }

            // Prevent moving to it's parent
            if ($target == $file['parent']) {
                $fault = true;
                continue;
            }

            // Prevent moving to it's children
            $path = array();
            $pathArr = array();
            $model->GetPath($target, $path);
            foreach ($path as $dir) {
                $pathArr[] = $dir['id'];
            }
            if (in_array($id, $pathArr)) {
                $fault = true;
                continue;
            }

            // Let's perform move
            // FIXME: we can move all files at once
            $res = $model->Move($id, $target);
            if (Jaws_Error::IsError($res)) {
                $fault = true;
                continue;
            }
        }

        if ($fault === true) {
            return $GLOBALS['app']->Session->GetResponse(
                _t('DIRECTORY_WARNING_MOVE'),
                RESPONSE_WARNING
            );
        } else {
            return $GLOBALS['app']->Session->GetResponse(
                _t('DIRECTORY_NOTICE_ITEMS_MOVED'),
                RESPONSE_NOTICE
            );
        }
    }

    /**
     * Searches among files and directories by passed query
     *
     * @access  public
     * @return  array   Response array
     */
    function Search()
    {
        $data = jaws()->request->fetch(array('id', 'file_filter', 'file_search',
            'file_type', 'file_size', 'start_date', 'end_date'));

        $jdate = Jaws_Date::getInstance();
        $start_date = $end_date = '';
        if (!empty($data['start_date'])) {
            $start_date = $jdate->ToBaseDate(preg_split('/[- :]/', $data['start_date']));
            $start_date = $GLOBALS['app']->UserTime2UTC($start_date['timestamp']);
        }
        if (!empty($data['end_date'])) {
            $end_date = $jdate->ToBaseDate(preg_split('/[- :]/', $data['end_date'].' 23:59:59'));
            $end_date = $GLOBALS['app']->UserTime2UTC($end_date['timestamp']);
        }
        $date = array($start_date, $end_date);

        $model = $this->gadget->model->loadAdmin('Files');
        $params = array();
        $params['parent'] = $data['id'];
        $params['query'] = $data['file_search'];
        $params['type'] = empty($data['file_type'])? null : $data['file_type'];
        $params['size'] = ($data['file_size'] == '0')? null : explode(',', $data['file_size']);
        $params['date'] = $date;
        $files = $model->GetFiles($params);
        if (Jaws_Error::IsError($files)){
            return $GLOBALS['app']->Session->GetResponse($files->getMessage(), RESPONSE_ERROR);
        }

        $objDate = Jaws_Date::getInstance();
        foreach ($files as &$file) {
            if ($file['is_dir']) {
                $file['url'] = BASE_SCRIPT . '?gadget=Directory&action=Directory&dirid=' . $file['id'];
            }
            $file['created'] = $objDate->Format($file['createtime'], 'n/j/Y g:i a');
            $file['modified'] = $objDate->Format($file['updatetime'], 'n/j/Y g:i a');
        }

        return $GLOBALS['app']->Session->GetResponse(
            _t('DIRECTORY_NOTICE_SEARCH_RESULT', count($files)),
            RESPONSE_NOTICE,
            $files);
    }
}