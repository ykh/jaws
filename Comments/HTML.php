<?php
/**
 * Comments Gadget
 *
 * @category   Gadget
 * @package    Comments
 * @author     Mojtaba Ebrahimi <ebrahimi@zehneziba.ir>
 * @copyright  2004-2013 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/gpl.html
 */
class Comments_HTML extends Jaws_Gadget_HTML
{
    /**
     * Calls default action(display)
     *
     * @access  public
     * @return  string  XHTML template content
     */
    function DefaultAction()
    {
        $layoutGadget = $GLOBALS['app']->LoadGadget('Comments', 'LayoutHTML');
        return $layoutGadget->Display();
    }

    /**
     * Adds a new entry to the comments, sets cookie with user data and redirects to main page
     *
     * @access  public
     * @return  void
     */
    function PostMessage()
    {
        $request =& Jaws_Request::getInstance();
        $post  = $request->get(array('message', 'name', 'email', 'url'), 'post');
        $model = $GLOBALS['app']->LoadGadget('Comments', 'Model');

        if ($GLOBALS['app']->Session->Logged()) {
            $post['name']  = $GLOBALS['app']->Session->GetAttribute('nickname');
            $post['email'] = $GLOBALS['app']->Session->GetAttribute('email');
            $post['url']   = $GLOBALS['app']->Session->GetAttribute('url');
        }

        if (trim($post['message']) == ''|| trim($post['name']) == '') {
            $GLOBALS['app']->Session->PushSimpleResponse(_t('COMMENTS_DONT_SEND_EMPTY_MESSAGES'), 'Comments');
            Jaws_Header::Referrer();
        }

        $mPolicy = $GLOBALS['app']->LoadGadget('Policy', 'Model');
        $resCheck = $mPolicy->CheckCaptcha();
        if (Jaws_Error::IsError($resCheck)) {
            $GLOBALS['app']->Session->PushSimpleResponse($resCheck->getMessage(), 'Comments');
            Jaws_Header::Referrer();
        }

        $permalink = $GLOBALS['app']->GetSiteURL();
        $status = $this->gadget->GetRegistry('comment_status');
        if ($GLOBALS['app']->Session->GetPermission('Comments', 'ManageComments')) {
            $status = COMMENT_STATUS_APPROVED;
        }

        $res = $model->NewComment(
            'comments', 0, '', $post['name'], $post['email'],
            $post['url'], $post['message'], $_SERVER['REMOTE_ADDR'], $permalink, 0, $status
        );


        if (Jaws_Error::isError($res)) {
            $GLOBALS['app']->Session->PushSimpleResponse($res->getMessage(), 'Comments');
        } else {
            $GLOBALS['app']->Session->PushSimpleResponse(_t('GLOBAL_MESSAGE_SENT'), 'Comments');
        }

        Jaws_Header::Referrer();
    }

    /**
     * Get page navigation links
     *
     * @access  public
     * @param   object  $tpl
     * @param   string  $base_block
     * @param   int     $page       page number
     * @param   int     $page_size  Entries count per page
     * @param   int     $total      Total entries count
     * @param   string  $total_string
     * @param   string  $action     Action name
     * @param   array   $params     Action params array
     * @return  string  XHTML template content
     */
    function GetPagesNavigation(&$tpl, $base_block, $page, $page_size, $total,
                                $total_string, $action, $params = array())
    {
        $pager = $this->GetNumberedPagesNavigation($page, $page_size, $total);
        if (count($pager) > 0) {
            $tpl->SetBlock("$base_block/pager");
            $tpl->SetVariable('total', $total_string);

            foreach ($pager as $k => $v) {
                $tpl->SetBlock("$base_block/pager/item");
                $params['page'] = $v;
                if ($k == 'next') {
                    if ($v) {
                        $tpl->SetBlock("$base_block/pager/item/next");
                        $tpl->SetVariable('lbl_next', _t('GLOBAL_NEXTPAGE'));
                        $url = $this->gadget->GetURLFor($action, $params);
                        $tpl->SetVariable('url_next', $url);
                        $tpl->ParseBlock("$base_block/pager/item/next");
                    } else {
                        $tpl->SetBlock("$base_block/pager/item/no_next");
                        $tpl->SetVariable('lbl_next', _t('GLOBAL_NEXTPAGE'));
                        $tpl->ParseBlock("$base_block/pager/item/no_next");
                    }
                } elseif ($k == 'previous') {
                    if ($v) {
                        $tpl->SetBlock("$base_block/pager/item/previous");
                        $tpl->SetVariable('lbl_previous', _t('GLOBAL_PREVIOUSPAGE'));
                        $url = $this->gadget->GetURLFor($action, $params);
                        $tpl->SetVariable('url_previous', $url);
                        $tpl->ParseBlock("$base_block/pager/item/previous");
                    } else {
                        $tpl->SetBlock("$base_block/pager/item/no_previous");
                        $tpl->SetVariable('lbl_previous', _t('GLOBAL_PREVIOUSPAGE'));
                        $tpl->ParseBlock("$base_block/pager/item/no_previous");
                    }
                } elseif ($k == 'separator1' || $k == 'separator2') {
                    $tpl->SetBlock("$base_block/pager/item/page_separator");
                    $tpl->ParseBlock("$base_block/pager/item/page_separator");
                } elseif ($k == 'current') {
                    $tpl->SetBlock("$base_block/pager/item/page_current");
                    $url = $this->gadget->GetURLFor($action, $params);
                    $tpl->SetVariable('lbl_page', $v);
                    $tpl->SetVariable('url_page', $url);
                    $tpl->ParseBlock("$base_block/pager/item/page_current");
                } elseif ($k != 'total' && $k != 'next' && $k != 'previous') {
                    $tpl->SetBlock("$base_block/pager/item/page_number");
                    $url = $this->gadget->GetURLFor($action, $params);
                    $tpl->SetVariable('lbl_page', $v);
                    $tpl->SetVariable('url_page', $url);
                    $tpl->ParseBlock("$base_block/pager/item/page_number");
                }
                $tpl->ParseBlock("$base_block/pager/item");
            }

            $tpl->ParseBlock("$base_block/pager");
        }
    }

    /**
     * Get numbered pages navigation
     *
     * @access  public
     * @param   int     $page      Current page number
     * @param   int     $page_size Entries count per page
     * @param   int     $total     Total entries count
     * @return  array   array with numbers of pages
     */
    function GetNumberedPagesNavigation($page, $page_size, $total)
    {
        $tail = 1;
        $paginator_size = 4;
        $pages = array();
        if ($page_size == 0) {
            return $pages;
        }

        $npages = ceil($total / $page_size);
        if ($npages < 2) {
            return $pages;
        }

        // Previous
        if ($page == 1) {
            $pages['previous'] = false;
        } else {
            $pages['previous'] = $page - 1;
        }

        if ($npages <= ($paginator_size + $tail)) {
            for ($i = 1; $i <= $npages; $i++) {
                if ($i == $page) {
                    $pages['current'] = $i;
                } else {
                    $pages[$i] = $i;
                }
            }
        } elseif ($page < $paginator_size) {
            for ($i = 1; $i <= $paginator_size; $i++) {
                if ($i == $page) {
                    $pages['current'] = $i;
                } else {
                    $pages[$i] = $i;
                }
            }

            $pages['separator2'] = true;
            for ($i = $npages - ($tail - 1); $i <= $npages; $i++) {
                $pages[$i] = $i;
            }
        } elseif ($page > ($npages - $paginator_size + $tail)) {
            for ($i = 1; $i <= $tail; $i++) {
                $pages[$i] = $i;
            }

            $pages['separator1'] = true;
            for ($i = $npages - $paginator_size + ($tail - 1); $i <= $npages; $i++) {
                if ($i == $page) {
                    $pages['current'] = $i;
                } else {
                    $pages[$i] = $i;
                }
            }
        } else {
            for ($i = 1; $i <= $tail; $i++) {
                $pages[$i] = $i;
            }

            $pages['separator1'] = true;
            $start = floor(($paginator_size - $tail)/2);
            $end = ($paginator_size - $tail) - $start;
            for ($i = $page - $start; $i < $page + $end; $i++) {
                if ($i == $page) {
                    $pages['current'] = $i;
                } else {
                    $pages[$i] = $i;
                }
            }

            $pages['separator2'] = true;
            for ($i = $npages - ($tail - 1); $i <= $npages; $i++) {
                $pages[$i] = $i;
            }
        }

        // Next
        if ($page == $npages) {
            $pages['next'] = false;
        } else {
            $pages['next'] = $page + 1;
        }

        $pages['total'] = $total;
        return $pages;
    }


}
