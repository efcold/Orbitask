function goToDate(date) {
    window.location.href = 'plan_date.php?date=' + date;
  }
 
 document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      events: '../handlers/cgp.php',
      height: "auto",
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay'
      },
      eventClick: function(info) {
        var planId = info.event.extendedProps.plan_id;
        var ownerUid = info.event.extendedProps.owner || '';
        var isOwner = info.event.extendedProps.isOwner;

        var viewUrl;
        if (isOwner) {
          viewUrl = "viewplan.php?plan_id=" + encodeURIComponent(planId) + 
                    "&owner_uid=" + encodeURIComponent(ownerUid);
        } else {
          viewUrl = "invites.php?plan_id=" + encodeURIComponent(planId) + 
                    "&owner_uid=" + encodeURIComponent(ownerUid);
        }

        window.location.href = viewUrl;
      }
    });
    
    calendar.render();
  });

