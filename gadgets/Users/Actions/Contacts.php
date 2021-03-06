<?php
/**
 * Users Core Gadget
 *
 * @category    Gadget
 * @package     Users
 * @author      Ali Fazelzadeh <afz@php.net>
 * @author      Mojtaba Ebrahimi <ebrahimi@zehneziba.ir>
 * @copyright   2013-2015 Jaws Development Group
 * @license     http://www.gnu.org/copyleft/lesser.html
 */
class Users_Actions_Contacts extends Users_Actions_Default
{
    /**
     * Prepares a simple form to update user's contacts information (country, city, ...)
     *
     * @access  public
     * @return  string  XHTML template of a form
     */
    function Contacts()
    {
        if (!$GLOBALS['app']->Session->Logged()) {
            Jaws_Header::Location(
                $this->gadget->urlMap(
                    'LoginBox',
                    array('referrer'  => bin2hex(Jaws_Utils::getRequestURL(true)))
                )
            );
        }

        $this->gadget->CheckPermission('EditUserContacts');
        $this->AjaxMe('index.js');
        $response = $GLOBALS['app']->Session->PopResponse('Users.Contacts');
        if (!isset($response['data'])) {
            $jUser = new Jaws_User;
            $contacts = $jUser->GetUser($GLOBALS['app']->Session->GetAttribute('user'), false, false, true);
        } else {
            $contacts = $response['data'];
        }

        // Load the template
        $tpl = $this->gadget->template->load('Contacts.html');
        $tpl->SetBlock('contacts');

        $tpl->SetVariable('title', _t('USERS_CONTACTS_INFO'));
        $tpl->SetVariable('base_script', BASE_SCRIPT);
        $tpl->SetVariable('update', _t('USERS_USERS_ACCOUNT_UPDATE'));

        // Menubar
        $tpl->SetVariable('menubar', $this->MenuBar('Account'));
        $tpl->SetVariable(
            'submenubar',
            $this->SubMenuBar('Contacts', array('Account', 'Personal', 'Preferences', 'Contacts'))
        );

        $tpl->SetVariable('lbl_country', _t('USERS_CONTACTS_COUNTRY'));
        $tpl->SetVariable('lbl_city', _t('USERS_CONTACTS_CITY'));
        $tpl->SetVariable('lbl_address', _t('USERS_CONTACTS_ADDRESS'));
        $tpl->SetVariable('lbl_postal_code', _t('USERS_CONTACTS_POSTAL_CODE'));
        $tpl->SetVariable('lbl_phone_number', _t('USERS_CONTACTS_PHONE_NUMBER'));
        $tpl->SetVariable('lbl_mobile_number', _t('USERS_CONTACTS_MOBILE_NUMBER'));
        $tpl->SetVariable('lbl_fax_number', _t('USERS_CONTACTS_FAX_NUMBER'));
        $tpl->SetVariablesArray($contacts);

        if (empty($contacts['avatar'])) {
            $user_current_avatar = $GLOBALS['app']->getSiteURL('/gadgets/Users/Resources/images/photo128px.png');
        } else {
            $user_current_avatar = $GLOBALS['app']->getDataURL() . "avatar/" . $contacts['avatar'];
            $user_current_avatar .= !empty($contacts['last_update']) ? "?" . $contacts['last_update'] . "" : '';
        }
        $avatar =& Piwi::CreateWidget('Image', $user_current_avatar);
        $avatar->SetID('avatar');
        $tpl->SetVariable('avatar', $avatar->Get());

        // countries list
        $ObjCountry = $this->gadget->model->load('Country');
        $countries = $ObjCountry->GetCountries();
        if (!Jaws_Error::IsError($Countries)) {
            array_unshift($countries, _t('USERS_ADVANCED_OPTS_NOT_YET'));
            foreach($countries as $code => $name) {
                $tpl->SetBlock('contacts/country');
                $tpl->SetVariable('code', $code);
                $tpl->SetVariable('name', $name);
                if ($contacts['country'] === $code) {
                    $tpl->SetBlock('contacts/country/selected');
                    $tpl->ParseBlock('contacts/country/selected');
                }
                $tpl->ParseBlock('contacts/country');
            }
        }

        if (!empty($response)) {
            $tpl->SetVariable('type', $response['type']);
            $tpl->SetVariable('text', $response['text']);
        }

        $tpl->ParseBlock('contacts');
        return $tpl->Get();
    }

    /**
     * Updates user contacts information
     *
     * @access  public
     * @return  void
     */
    function UpdateContacts()
    {
        if (!$GLOBALS['app']->Session->Logged()) {
            Jaws_Header::Location(
                $this->gadget->urlMap(
                    'LoginBox',
                    array('referrer'  => bin2hex(Jaws_Utils::getRequestURL(true)))
                )
            );
        }

        $this->gadget->CheckPermission('EditUserContacts');
        $post = jaws()->request->fetch(
            array(
                'country', 'city', 'address', 'postal_code', 'phone_number',
                'mobile_number', 'fax_number'
            ),
            'post'
        );

        $uModel = $this->gadget->model->load('Contacts');
        $result = $uModel->UpdateContacts(
            $GLOBALS['app']->Session->GetAttribute('user'),
            $post['country'],
            $post['city'],
            $post['address'],
            $post['postal_code'],
            $post['phone_number'],
            $post['mobile_number'],
            $post['fax_number']
        );
        if (Jaws_Error::IsError($result)) {
            $GLOBALS['app']->Session->PushResponse(
                $result->GetMessage(),
                'Users.Contacts',
                RESPONSE_ERROR,
                $post
            );
        } else {
            $GLOBALS['app']->Session->PushResponse(
                _t('USERS_USERS_CONTACTINFO_UPDATED'),
                'Users.Contacts'
            );
        }

        Jaws_Header::Location($this->gadget->urlMap('Contacts'), 'Users.Contacts');
    }

}