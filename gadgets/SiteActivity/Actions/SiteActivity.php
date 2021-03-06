<?php
/**
 * SiteActivity Gadget
 *
 * @category    Gadget
 * @package     Subscription
 */
class SiteActivity_Actions_SiteActivity extends Jaws_Gadget_Action
{
    /**
     * Send data to parent site
     *
     * @access  public
     * @return  boolean
     */
    function SiteActivity()
    {
        // Load the template
        $tpl = $this->gadget->template->load('SiteActivity.html');
        $tpl->SetBlock('SiteActivity');
        $tpl->SetVariable('title', _t('SITEACTIVITY_ACTIONS_SITEACTIVITY'));
        $this->SetTitle(_t('SITEACTIVITY_ACTIONS_SITEACTIVITY'));

        $model = $this->gadget->model->load('SiteActivity');

        $filters = array();
        $today = getdate();
        $todayTime = mktime(0, 0, 0, $today['mon'], $today['mday'], $today['year']);
        $filters['domain'] = '-1'; // fetch just own domain data
        $filters['from_date'] = $todayTime; // fetch today data
        $activities = $model->GetSiteActivities($filters);

        if (!Jaws_Error::isError($activities) && !empty($activities)) {
            $gadgetsActivities = array();
            $gadget = '';
            foreach ($activities as $activity) {
                if ($activity['gadget'] != $gadget) {
                    $gadget = $activity['gadget'];
                }
                $gadgetsActivities[$gadget][$activity['action']] = $activity['hits'];
            }
        }

        $saGadgets = $model->GetSiteActivityGadgets();
        if(count($saGadgets)>0) {
            foreach ($saGadgets as $gadget => $gTitle) {
                // load gadget
                $objGadget = Jaws_Gadget::getInstance($gadget);
                if (Jaws_Error::IsError($objGadget)) {
                    continue;
                }
                // load hook
                $objHook = $objGadget->hook->load('SiteActivity');
                if (Jaws_Error::IsError($objHook)) {
                    continue;
                }
                // fetch gadget activity's action items
                $actions = $objHook->Execute();

                $tpl->SetBlock('SiteActivity/gadget');

                $tpl->SetVariable('gadget_title', $objGadget->name);
                foreach ($actions as $actionName => $actionTitle) {
                    $tpl->SetBlock('SiteActivity/gadget/action');
                    $tpl->SetVariable('action', $actionTitle);
                    $hits = isset($gadgetsActivities[$gadget][$actionName]) ?
                        $gadgetsActivities[$gadget][$actionName] : 0;
                    $tpl->SetVariable('hits', $hits);
                    $tpl->ParseBlock('SiteActivity/gadget/action');

                }
                $tpl->ParseBlock('SiteActivity/gadget');
            }
        } else {
            $tpl->SetBlock('SiteActivity/no_activity');
            $tpl->SetVariable('no_activity', _t('SITEACTIVITY_ACTIONS_NOT_FIND_ACTIVITY'));
            $tpl->ParseBlock('SiteActivity/no_activity');
        }

        $tpl->ParseBlock('SiteActivity');
        return $tpl->Get();

    }

    /**
     * Send data to parent site
     *
     * @access  public
     * @return  boolean
     */
    function SendData()
    {
        $processing = $this->gadget->registry->fetch('processing');
        $lastUpdate = (int)$this->gadget->registry->fetch('last_update');
        $queueMaxTime = (int)$this->gadget->registry->fetch('queue_max_time');
        if ($processing == 'true' && $lastUpdate + $queueMaxTime < time()) {
            return false;
        }

        $this->gadget->registry->update('last_update', time());
        $this->gadget->registry->update('processing', 'true');

        $model = $this->gadget->model->load('SiteActivity');
        $activities = $model->GetSiteActivities();
        if (Jaws_Error::IsError($activities)) {
            $this->gadget->registry->update('processing', 'false');
            return $activities;
        }

        $activityIds = array();
        foreach ($activities as $activity) {
            $activityIds[] = $activity['id'];
        }

        // post data to parent site
        $parentURL = $this->gadget->registry->fetch('parent_url');
        $httpRequest = new Jaws_HTTPRequest();
        $result = $httpRequest->post($parentURL, $data, $retData);
        if (Jaws_Error::IsError($result) || $result != 200) {
            $this->gadget->registry->update('processing', 'false');
            return false;
        }

        // update sync status
        $model->UpdateSiteActivitySync($activityIds, true);

        // finish procession
        $this->gadget->registry->update('processing', 'false');
        return $retData;
    }


    /**
     * Receive data from sub site
     *
     * @access  public
     * @return bool
     */
    function ReceiveData()
    {
//        $post = jaws()->request->fetch(array('data:array'), 'post');
        $data = jaws()->request->fetchAll('post');
        return var_export($data, true);

        // insert activity data
        $model = $this->gadget->model->load('SiteActivity');
        $model->InsertSiteActivity($data);


    }
}