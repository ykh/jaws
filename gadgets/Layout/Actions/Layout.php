<?php
/**
 * Layout Gadget
 *
 * @category    GadgetAdmin
 * @package     Layout
 * @author      Ali Fazelzadeh <afz@php.net>
 * @copyright   2013 Jaws Development Group
 * @license     http://www.gnu.org/copyleft/lesser.html
 */
class Layout_Actions_Layout extends Jaws_Gadget_HTML
{
    /**
     *
     */
    function Layout()
    {
        $user = jaws()->request->fetch('user');
        // permission check
        $layoutModel = $this->gadget->load('Model')->load('Model', 'Layout');
        $layoutModel->layoutSwitch($user);
        Jaws_Header::Location('');
    }

}