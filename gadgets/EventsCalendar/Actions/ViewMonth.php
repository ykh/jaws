<?php
/**
 * EventsCalendar Gadget
 *
 * @category    Gadget
 * @package     EventsCalendar
 * @author      Mohsen Khahani <mkhahani@gmail.com>
 * @copyright   2013 Jaws Development Group
 * @license     http://www.gnu.org/copyleft/gpl.html
 */
$GLOBALS['app']->Layout->AddHeadLink('gadgets/EventsCalendar/Resources/site_style.css');
class EventsCalendar_Actions_ViewMonth extends Jaws_Gadget_HTML
{
    /**
     * Builds month view UI
     *
     * @access  public
     * @return  string  XHTML UI
     */
    function ViewMonth()
    {
        $data = jaws()->request->fetch(array('year', 'month'), 'get');
        $year = (int)$data['year'];
        $month = (int)$data['month'];

        $this->AjaxMe('site_script.js');
        $tpl = $this->gadget->loadTemplate('ViewMonth.html');
        $tpl->SetBlock('month');

        $this->SetTitle(_t('EVENTSCALENDAR_VIEW_MONTH'));
        $tpl->SetVariable('title', _t('EVENTSCALENDAR_VIEW_MONTH'));
        $tpl->SetVariable('lbl_day', _t('EVENTSCALENDAR_DAY'));
        $tpl->SetVariable('lbl_events', _t('EVENTSCALENDAR_EVENTS'));

        $daysInMonth = 30;
        $jdate = $GLOBALS['app']->loadDate();
        $start = $jdate->ToBaseDate($year, $month, 1);
        $start = $start['timestamp'];
        $stop = $jdate->ToBaseDate($year, $month, $daysInMonth, 23, 59, 59);
        $stop = $stop['timestamp'];

        // Current date
        $tpl->SetVariable('current_date', $year . ' ' . $jdate->Format($start, 'MN'));

        // Previous month
        $prevYear = $year;
        $prevMonth = $month - 1;
        if ($prevMonth === 0) {
            $prevMonth = 12;
            $prevYear--;
        }
        $prevURL = $this->gadget->urlMap('ViewMonth', array(
            'year' => $prevYear,
            'month' => $prevMonth
        ));
        $tpl->SetVariable('prev', $prevURL);
        $tpl->SetVariable('prev_year', $prevYear);

        // Next month
        $nextYear = $year;
        $nextMonth = $month + 1;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }
        $nextURL = $this->gadget->urlMap('ViewMonth', array(
            'year' => $nextYear,
            'month' => $nextMonth
        ));
        $tpl->SetVariable('next', $nextURL);
        $tpl->SetVariable('next_year', $nextYear);

        // Fetch events
        $model = $GLOBALS['app']->LoadGadget('EventsCalendar', 'Model', 'Month');
        $user = (int)$GLOBALS['app']->Session->GetAttribute('user');
        $events = $model->GetEvents($user, null, null, $start, $stop);
        if (Jaws_Error::IsError($events)){
            $events = array();
        }

        // Prepare events
        $eventsById = array();
        $eventsByDay = array_fill(1, $daysInMonth, array());
        foreach ($events as $e) {
            $eventsById[$e['id']] = $e;
            $startIdx = ($e['start_time'] <= $start)? 1:
                ceil(($e['start_time'] - $start) / 86400);
            $stopIdx = ($e['stop_time'] >= $stop)? $daysInMonth:
                ceil(($e['stop_time'] - $start) / 86400);
            for ($i = $startIdx; $i <= $stopIdx; $i++) {
                $eventsByDay[$i][] = $e['id'];
            }
        }

        // Display events
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $date = $jdate->ToBaseDate($year, $month, $i);
            $weekDay = $jdate->Format($date['timestamp'], 'DN');
            $tpl->SetBlock('month/day');
            $tpl->SetVariable('day', $i . ' ' . $weekDay);
            foreach ($eventsByDay[$i] as $event_id) {
                $tpl->SetBlock('month/day/event');
                $tpl->SetVariable('event', $eventsById[$event_id]['subject']);
                $tpl->ParseBlock('month/day/event');
            }
            $tpl->ParseBlock('month/day');
        }

        $tpl->ParseBlock('month');
        return $tpl->Get();
    }
}