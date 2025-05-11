document.addEventListener('DOMContentLoaded', () => {
  const calendar = document.getElementById('smallCalendar');
  const calendarMonthDisplay = document.getElementById('calendarMonth');
  const prevMonthBtn = document.getElementById('prevMonth');
  const nextMonthBtn = document.getElementById('nextMonth');

  let currentDate = new Date();

  const updateCalendar = () => {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const taskDates = Object.values(tasks)
      .map(task => task.due_date)
      .filter(Boolean);

    calendar.innerHTML = '';

    calendarMonthDisplay.textContent = 
    `${currentDate.toLocaleString('default', { month: 'long' })} ${year}`;

    const table = document.createElement('table');
    table.style.width = '100%';

    const header = document.createElement('tr');
    ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
      const th = document.createElement('th');
      th.textContent = day;
      header.appendChild(th);
    });
    table.appendChild(header);

    let date = 1;
    for (let i = 0; i < 6; i++) {
      const row = document.createElement('tr');

      for (let j = 0; j < 7; j++) {
        const cell = document.createElement('td');

        if (i === 0 && j < firstDay) {
          cell.textContent = '';
        } else if (date > daysInMonth) {
          cell.textContent = '';  
        } else {
          const fullDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
          cell.textContent = date;
          cell.className = 'calendar-day';
          if (taskDates.includes(fullDate)) {
            cell.classList.add('task-date');
          }
          date++;
        }

        row.appendChild(cell);
      }

      table.appendChild(row);
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
