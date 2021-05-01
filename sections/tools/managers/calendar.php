<?php declare(strict_types=1);
if (!Calendar::can_view()) {
    error(404);
}

$Month = $_GET['month'];
$Year = $_GET['year'];

if (empty($Month) || empty($Year)) {
    $Date = getdate();
    $Month = $Date['mon'];
    $Year = $Date['year'];
}

$Events = Calendar::get_events($Month, $Year);
View::show_header("Calendar", "jquery.validate,form_validate,calendar", "calendar");

CalendarView::render_title($Month, $Year);
?>
<div class="sidebar">
  <div id="event_div"></div>
</div>
<div class="main_column">
<?php
  CalendarView::render_calendar($Month, $Year, $Events);
?>
</div>
<?php
View::show_footer();
