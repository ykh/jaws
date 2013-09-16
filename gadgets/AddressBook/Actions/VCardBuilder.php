<?php
/**
 * AddressBook Gadget
 *
 * @category   GadgetAdmin
 * @package    AddressBook
 * @author     HamidReza Aboutalebi <hamid@aboutalebi.com>
 * @copyright  2013 Jaws Development Group
 */
$GLOBALS['app']->Layout->AddHeadLink('gadgets/AddressBook/resources/site_style.css');
class AddressBook_Actions_VCardBuilder extends AddressBook_HTML
{
    /**
     * Build and export data with VCard format
     *
     * @access  public
     * @return  string HTML content with menu and menu items
     */
    function VCardBuild()
    {
        if (!$GLOBALS['app']->Session->Logged()) {
            return Jaws_HTTPError::Get(403);
        }

        require_once JAWS_PATH . 'gadgets/Addressbook/vCard.php';

        $model = $this->gadget->load('Model')->load('Model', 'AddressBook');
        $agModel = $this->gadget->load('Model')->load('Model', 'AddressBookGroup');
        $user = (int) $GLOBALS['app']->Session->GetAttribute('user');
        $post = jaws()->request->fetch(array('address:int', 'group:int', 'term'));

        $addressItems = $model->GetAddressList($user, $post['group'], false, $post['term']);
        if (Jaws_Error::IsError($addressItems) || empty($addressItems)) {
            return Jaws_HTTPError::Get(404);
        }

        $result = '';
        $nVCard = array('LastName', 'FirstName', 'AdditionalNames', 'Prefixes', 'Suffixes');
        foreach ($addressItems as $addressItem) {
            $vCard = new vCard;

            $names = explode(';', $addressItem['name']);
            foreach ($names as $key => $name) {
                 $vCard->n($name, $nVCard[$key]);
            }
            $vCard->fn($names[3] . (trim($names[3]) == '' ?  '' : ' ') . $names[1] . (trim($names[1]) == '' ? '' : ' ') . $names[0]);
            $vCard->nickname($addressItem['nickname']);

            $adrGroups = $agModel->GetGroupNames($addressItem['address_id'], $user);
            $vCard->categories(implode(',', $adrGroups));

            $this->FillVCardTypes($vCard, 'tel', $addressItem['tel_home'], $this->_TelTypes);
            $this->FillVCardTypes($vCard, 'tel', $addressItem['tel_work'], $this->_TelTypes);
            $this->FillVCardTypes($vCard, 'tel', $addressItem['tel_other'], $this->_TelTypes);

            $this->FillVCardTypes($vCard, 'email', $addressItem['email_home'], $this->_EmailTypes);
            $this->FillVCardTypes($vCard, 'email', $addressItem['email_work'], $this->_EmailTypes);
            $this->FillVCardTypes($vCard, 'email', $addressItem['email_other'], $this->_EmailTypes);

            $this->FillVCardTypes($vCard, 'adr', $addressItem['adr_home'], $this->_AdrTypes);
            $this->FillVCardTypes($vCard, 'adr', $addressItem['adr_work'], $this->_AdrTypes);
            $this->FillVCardTypes($vCard, 'adr', $addressItem['adr_other'], $this->_AdrTypes);

            $this->FillVCardTypes($vCard, 'url', $addressItem['url']);
            $vCard->note($addressItem['notes']);

            $result = $result . $vCard;
        }

        header("Content-Disposition: attachment; filename=\"" . 'address.vcf' . "\"");
        header("Content-type: application/csv");
        header("Content-Length: " . strlen($result));
        header("Pragma: no-cache");
        header("Expires: 0");
        header("Connection: close");

        echo $result;
        exit;
    }

    /**
     * Fill data in vcard format
     *
     * @access  public
     * @param   object  $vCard
     * @param   string  $base_block
     * @param   array   $inputValue
     * @param   array   $options
     * @return  string  XHTML template content
     */
    function FillVCardTypes(&$vCard, $dataType, $inputValue, $options = null)
    {
        if (trim($inputValue) == '') {
            return;
        }
        $inputValue = explode(',', trim($inputValue));
        foreach ($inputValue as $val) {
            $result = explode(':', $val);
            if ($dataType == 'tel') {
                $vCard->tel($result[1], $options[$result[0]]['fieldType'], $options[$result[0]]['telType']);
            } else if ($dataType == 'adr') {
                //$vCard->adr('', $options[$result[0]]['fieldType']);
                //$vCard->adr($result[1], 'ExtendedAddress');
                $vCard->label($result[1], $options[$result[0]]['fieldType']);
            } else if ($dataType == 'url') {
                $vCard->url($val);
            } else {
                $vCard->$dataType($result[1], $options[$result[0]]['fieldType']);
            }
        }
    }
}