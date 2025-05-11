document.addEventListener('DOMContentLoaded', () => {
  const calendar              = document.getElementById('smallCalendar');
  const calendarMonthDisplay  = document.getElementById('calendarMonth');
  const prevMonthBtn          = document.getElementById('prevMonth');
  const nextMonthBtn          = document.getElementById('nextMonth');
  let currentDate             = new Date();

  const updateCalendar = () => {
    const year        = currentDate.getFullYear();
    const month       = currentDate.getMonth();
    const firstDay    = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const taskDates = Object.values(tasks)
      .map(t => t.due_date)
      .filter(Boolean);

    calendar.innerHTML = '';
    calendarMonthDisplay.textContent =
      `${currentDate.toLocaleString('default',{month:'long'})} ${year}`;

    const table = document.createElement('table');
    table.style.width = '100%';
    const header = document.createElement('tr');
    ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(d => {
      const th = document.createElement('th');
      th.textContent = d;
      header.appendChild(th);
    });
    table.appendChild(header);

    let date = 1;
    for (let row = 0; row < 6; row++) {
      const tr = document.createElement('tr');
      for (let col = 0; col < 7; col++) {
        const td = document.createElement('td');
        if (row === 0 && col < firstDay) {
          td.textContent = '';
        } else if (date > daysInMonth) {
          td.textContent = '';
        } else {
          const iso = `${year}-${String(month+1).padStart(2,'0')}-${String(date).padStart(2,'0')}`;
          td.textContent = date;
          td.classList.add('calendar-day');
          if (taskDates.includes(iso)) td.classList.add('task-date');
          date++;
        }
        tr.appendChild(td);
      }
      table.appendChild(tr);
    }
    calendar.appendChild(table);
  };

  prevMonthBtn.addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() - 1);
    updateCalendar();
  });
  nextMonthBtn.addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() + 1);
    updateCalendar();
  });

  updateCalendar();
});