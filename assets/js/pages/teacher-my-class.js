(() => {
  'use strict';

  const host = document.querySelector('[data-my-classes-workspace]');
  if (!host) return;

  const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  const shortDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const classes = JSON.parse(host.dataset.classes || '[]');
  const weekDates = JSON.parse(host.dataset.weekDates || '{}');
  const grid = host.querySelector('[data-calendar-grid]');
  const detail = host.querySelector('[data-class-detail]');
  const banner = host.querySelector('[data-edit-banner]');
  let selectedId = 0;
  let editing = false;
  let draft = [];
  let baseline = '';
  let statusMessage = '';

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[character]);
  const getClass = (id) => classes.find((item) => Number(item.operationalClassId) === Number(id));
  const clockMinutes = (value) => {
    const [hour, minute] = String(value || '00:00').split(':').map(Number);
    return hour * 60 + minute;
  };
  const displayTime = (value) => {
    const [hour, minute] = String(value || '').split(':').map(Number);
    if (!Number.isFinite(hour) || !Number.isFinite(minute)) return value || '—';
    const suffix = hour >= 12 ? 'PM' : 'AM';
    return `${hour % 12 || 12}:${String(minute).padStart(2, '0')} ${suffix}`;
  };
  const allMeetings = () => classes.flatMap((item, classIndex) => (item.schedules || []).map((meeting) => ({
    ...meeting, operationalClassId: Number(item.operationalClassId), subject: item.subject,
    section: item.section, classIndex
  })));
  const selectedClass = () => getClass(selectedId);
  const scheduleCopy = (items) => JSON.stringify(items.map(({ classScheduleId, day, startTime, endTime, roomValue, room }) => ({
    classScheduleId: classScheduleId || null, day, startTime, endTime, room: roomValue ?? room ?? ''
  })));
  const getVisibleMeetings = () => editing
    ? allMeetings().filter((meeting) => meeting.operationalClassId !== selectedId).concat(draft.map((meeting, index) => ({
      ...meeting, classScheduleId: meeting.classScheduleId || null, operationalClassId: selectedId,
      subject: selectedClass()?.subject, section: selectedClass()?.section, draftIndex: index,
      classIndex: Math.max(0, classes.findIndex((item) => Number(item.operationalClassId) === selectedId)), isDraft: true
    })))
    : allMeetings();

  function conflictFor(meeting, index) {
    const candidates = editing ? allMeetings().filter((item) => item.operationalClassId !== selectedId) : allMeetings();
    const ownDraft = editing ? draft.map((item, draftIndex) => ({ ...item, operationalClassId: selectedId, draftIndex })) : [];
    for (const other of [...candidates, ...ownDraft]) {
      if (other.draftIndex === index || other.classScheduleId === meeting.classScheduleId) continue;
      if (other.day !== meeting.day || clockMinutes(meeting.startTime) >= clockMinutes(other.endTime) || clockMinutes(meeting.endTime) <= clockMinutes(other.startTime)) continue;
      if (other.operationalClassId === selectedId) return 'This class already has another meeting at that time.';
      if (String(other.roomValue || other.room || '').trim() && String(meeting.room || meeting.roomValue || '').trim().toLowerCase() === String(other.roomValue || other.room).trim().toLowerCase()) return `Room ${meeting.room || meeting.roomValue} is already occupied at that time.`;
      if (Number(other.teacherId || 0) === Number(selectedClass()?.teacherId || 0)) return 'This time conflicts with another class assigned to you.';
      if (other.section === selectedClass()?.section) return 'This time conflicts with another class assigned to the same Section.';
      return 'This time conflicts with another scheduled class.';
    }
    return '';
  }

  function renderCalendar() {
    const meetings = getVisibleMeetings();
    const startBound = Math.min(7 * 60, ...meetings.map((item) => clockMinutes(item.startTime)));
    const endBound = Math.max(19 * 60, ...meetings.map((item) => clockMinutes(item.endTime)));
    const firstHour = Math.max(0, Math.floor(startBound / 60));
    const lastHour = Math.ceil(endBound / 60);
    const hours = Math.max(1, lastHour - firstHour);
    const today = host.dataset.today;
    const meetingMarkup = (meeting) => {
      const start = clockMinutes(meeting.startTime); const duration = clockMinutes(meeting.endTime) - start;
      const top = Math.max(0, (start - firstHour * 60) * 64 / 60); const height = Math.max(1, duration * 64 / 60 - 4);
      const density = height < 42 ? 'is-ultra-compact' : height < 112 ? 'is-compact' : '';
      const selected = Number(meeting.operationalClassId) === selectedId;
      const conflict = meeting.isDraft ? conflictFor(meeting, meeting.draftIndex) : '';
      const dimmed = editing && !selected;
      return `<button type="button" class="teacher-my-classes-meeting ${density} ${selected ? 'is-selected' : ''} ${meeting.isDraft ? 'is-draft' : ''} ${conflict ? 'is-conflict' : ''} ${dimmed ? 'is-dimmed' : ''} tone-${meeting.classIndex % 4}" data-meeting-class="${meeting.operationalClassId}" ${meeting.isDraft ? `data-meeting-draft="${meeting.draftIndex}"` : ''} style="top:${top}px;height:${height}px" aria-pressed="${selected}" aria-label="${escapeHtml(`${meeting.subject}, ${meeting.section}, ${meeting.day}, ${displayTime(meeting.startTime)} to ${displayTime(meeting.endTime)}${meeting.roomValue || meeting.room ? `, ${meeting.roomValue || meeting.room}` : ''}${conflict ? `, conflict: ${conflict}` : ''}`)}"><strong>${escapeHtml(meeting.subject)}</strong><span>${displayTime(meeting.startTime)}–${displayTime(meeting.endTime)}</span><small>${escapeHtml(meeting.section)} · ${escapeHtml(meeting.roomValue || meeting.room || 'Room not set')}</small>${conflict ? '<em>Conflict</em>' : ''}</button>`;
    };
    grid.innerHTML = `<div class="teacher-my-classes-calendar-head"><span></span>${days.map((day) => `<div class="${day === today ? 'is-today' : ''}"><span>${day.slice(0, 3)}</span><strong>${escapeHtml(weekDates[day] || '')}</strong>${day === today ? '<small>Today</small>' : ''}</div>`).join('')}</div><div class="teacher-my-classes-calendar-body"><div class="teacher-my-classes-time-axis">${Array.from({ length: hours + 1 }, (_, index) => `<span class="${index === hours ? 'is-end' : ''}" style="top:${index * 64}px">${((firstHour + index) % 12 || 12)} ${firstHour + index >= 12 ? 'PM' : 'AM'}</span>`).join('')}</div>${days.map((day) => `<div class="teacher-my-classes-day-column ${day === today ? 'is-today' : ''}" data-day-column="${day}" style="height:${hours * 64}px">${editing ? Array.from({ length: hours }, (_, index) => `<button type="button" class="teacher-my-classes-empty-slot" data-add-slot data-day="${day}" data-start="${String(firstHour + index).padStart(2, '0')}:00" style="top:${index * 64}px;height:64px" aria-label="Add a meeting on ${day} at ${displayTime(`${String(firstHour + index).padStart(2, '0')}:00`)}"></button>`).join('') : ''}${meetings.filter((meeting) => meeting.day === day).map(meetingMarkup).join('')}</div>`).join('')}</div>`;
    grid.style.setProperty('--calendar-hours', hours);
    grid.closest('.teacher-my-classes-calendar-scroll')?.classList.toggle('is-long-range', hours > 14);
    grid.dataset.firstHour = String(firstHour);
    grid.dataset.lastHour = String(lastHour);
  }

  function renderDetail() {
    const item = selectedClass();
    if (!item) {
      detail.classList.remove('is-editing');
      detail.style.minHeight = '';
      detail.innerHTML = '<div class="teacher-my-classes-empty-detail">Select a class on the calendar to see its details and actions.</div>';
      return;
    }
    const meetings = editing ? draft : (item.schedules || []).map((schedule) => ({ ...schedule, room: schedule.roomValue || '' }));
    const conflicts = editing ? draft.map((meeting, index) => conflictFor(meeting, index)).filter(Boolean) : [];
    const invalid = editing && draft.some((meeting) => !meeting.day || !meeting.startTime || !meeting.endTime || clockMinutes(meeting.endTime) <= clockMinutes(meeting.startTime) || String(meeting.room || '').length > 100);
    const dirty = editing && scheduleCopy(draft) !== baseline;
    const hasRoster = Number(item.rosterCount) > 0;
    const classId = Number(item.operationalClassId);
    const links = [
      `<a href="${APP_URL}/dashboard/teacher_class_list.php?operational_class_id=${classId}">Class List</a>`,
      `<a href="${APP_URL}/dashboard/teacher_april_tags.php?operational_class_id=${classId}">AprilTags</a>`,
      `<a href="${APP_URL}/dashboard/teacher_grade_import.php?operational_class_id=${classId}">Grade Import</a>`,
      `<a href="${APP_URL}/dashboard/teacher_grade_ledger.php?class_id=${classId}">Grade Ledger</a>`,
      `<a href="${APP_URL}/dashboard/teacher_grade_analytics.php?class_id=${classId}">Grade Analytics</a>`,
      `<a href="${APP_URL}/dashboard/teacher_analytics.php?class_id=${classId}">Class Analytics</a>`,
      `<a href="${APP_URL}/dashboard/teacher_reports.php?class_id=${classId}">Reports</a>`,
      hasRoster ? `<a href="${APP_URL}/dashboard/teacher_referrals.php?class_id=${classId}">Create Referral</a>` : ''
    ].filter(Boolean).join('');
    const meetingRows = meetings.map((meeting, index) => {
      if (!editing) return `<div class="teacher-my-classes-meeting-row ${meeting.day === host.dataset.today ? 'is-today' : ''}"><div><strong>${escapeHtml(meeting.day)}${meeting.day === host.dataset.today ? ' · Today' : ''}</strong><span>${displayTime(meeting.startTime)} – ${displayTime(meeting.endTime)}</span></div><span class="teacher-my-classes-room"><i data-lucide="map-pin" aria-hidden="true"></i>${escapeHtml(meeting.roomValue || meeting.room || 'Room not set')}</span></div>`;
      return `<fieldset class="teacher-my-classes-editor-meeting" data-editor-meeting="${index}"><legend>Meeting ${index + 1}</legend><div class="teacher-my-classes-day-picks" role="group" aria-label="Meeting ${index + 1} day">${shortDays.map((day, dayIndex) => `<button type="button" data-day-pick="${days[dayIndex]}" class="${meeting.day === days[dayIndex] ? 'is-active' : ''}" aria-pressed="${meeting.day === days[dayIndex]}">${day}</button>`).join('')}</div><div class="teacher-my-classes-editor-fields"><label>Start<input type="time" data-meeting-field="startTime" value="${escapeHtml(meeting.startTime)}"></label><label>End<input type="time" data-meeting-field="endTime" value="${escapeHtml(meeting.endTime)}"></label><label class="teacher-my-classes-room-field">Room<input type="text" maxlength="100" data-meeting-field="room" value="${escapeHtml(meeting.room || meeting.roomValue || '')}" placeholder="Optional"></label><button type="button" class="teacher-my-classes-remove" data-remove-meeting aria-label="Remove meeting ${index + 1}">Remove</button></div>${conflicts[index] ? `<p class="teacher-my-classes-conflict">${escapeHtml(conflicts[index])}</p>` : ''}</fieldset>`;
    }).join('');
    const attendanceReady = hasRoster;
    const primary = attendanceReady
      ? `<a class="teacher-primary-btn teacher-my-classes-take-attendance" href="${APP_URL}/dashboard/teacher_attendance_scanner.php?operational_class_id=${classId}">Take attendance</a>`
      : `<a class="teacher-primary-btn teacher-my-classes-take-attendance" href="${APP_URL}/dashboard/teacher_class_list.php?operational_class_id=${classId}">Open class list</a>`;
    detail.classList.toggle('is-editing', editing);
    detail.innerHTML = `<header class="teacher-my-classes-detail-heading"><div><h2>${escapeHtml(item.subject)}</h2><div class="teacher-my-classes-subline"><span>${escapeHtml(item.subjectCode || '')}</span><strong>${escapeHtml(item.section)}</strong><span>${Number(item.rosterCount)} ${Number(item.rosterCount) === 1 ? 'student' : 'students'}</span></div><span class="teacher-my-classes-readiness ${hasRoster ? 'is-ready' : 'is-attention'}">${hasRoster ? 'Ready for attendance' : 'Needs class list'}</span></div>${editing ? '' : '<button type="button" class="teacher-my-classes-close-detail" data-clear-selection aria-label="Close class details">×</button>'}</header>
      <div class="teacher-my-classes-context-grid"><span>Academic Level</span><strong>${escapeHtml(item.academicLevel || 'Not confirmed')}</strong><span>School Year</span><strong>${escapeHtml(item.schoolYear)}</strong><span>Teaching term</span><strong>${escapeHtml(item.semester)}</strong></div>
      <section class="teacher-my-classes-detail-section"><div class="teacher-my-classes-section-heading"><h3>${editing ? 'Edit schedule' : 'Meetings'}</h3><span>${meetings.length} ${meetings.length === 1 ? 'meeting' : 'meetings'}${editing ? '' : ' a week'}</span></div>${meetings.length ? `<div class="teacher-my-classes-meetings">${meetingRows}</div>` : '<p class="teacher-my-classes-no-meetings">No meetings are scheduled. Choose Edit schedule to add one.</p>'}</section>
      ${editing ? `<div class="teacher-my-classes-edit-summary ${conflicts.length || invalid ? 'is-invalid' : ''}" role="status">${conflicts.length ? escapeHtml(conflicts[0]) : invalid ? 'Complete the meeting details and make sure each end time is later than its start time.' : dirty ? 'Schedule changes are ready to save.' : 'No unsaved changes.'}</div><button type="button" class="teacher-my-classes-add-meeting" data-add-meeting>＋ Add meeting</button><div class="teacher-my-classes-edit-actions"><button type="button" class="teacher-my-classes-cancel" data-cancel-edit>Cancel</button><button type="button" class="teacher-primary-btn" data-save-schedule ${(!dirty || conflicts.length || invalid) ? 'disabled' : ''}>Save schedule</button></div>` : `<section class="teacher-my-classes-detail-section"><div class="teacher-my-classes-section-heading"><h3>Setup</h3><span>Class readiness</span></div><ol class="teacher-my-classes-setup"><li class="${hasRoster ? 'is-done' : 'is-current'}"><span>1</span><div><strong>Class list</strong><small>${hasRoster ? 'Imported' : 'Needs class list'}</small></div><a href="${APP_URL}/dashboard/teacher_class_list.php?operational_class_id=${classId}" aria-label="Open class list">›</a></li><li class="${Number(item.aprilTagCount) > 0 ? 'is-done' : hasRoster ? 'is-current' : ''}"><span>2</span><div><strong>AprilTags</strong><small>${Number(item.aprilTagCount) > 0 ? 'Ready' : hasRoster ? 'Not generated' : 'Awaiting class list'}</small></div><a href="${APP_URL}/dashboard/teacher_april_tags.php?operational_class_id=${classId}" aria-label="Open AprilTags">›</a></li><li class="${item.attendanceStatus === 'Open' ? 'is-done' : ''}"><span>3</span><div><strong>Attendance</strong><small>${escapeHtml(item.attendanceStatus || 'Not started')}</small></div></li></ol></section><div class="teacher-my-classes-actions">${primary}<div class="teacher-my-classes-secondary-actions"><button type="button" data-edit-schedule>Edit schedule</button><details><summary>Manage <span aria-hidden="true">⌄</span></summary><nav aria-label="Class actions">${links}</nav></details></div></div>`}`;
  }

  function render() {
    banner.hidden = !editing;
    renderCalendar();
    renderDetail();
    requestAnimationFrame(balanceDetailShell);
    if (window.lucide) window.lucide.createIcons();
  }

  function balanceDetailShell() {
    detail.style.minHeight = '';
    detail.style.boxSizing = '';
    if (!selectedId || editing || !window.matchMedia('(min-width: 1101px)').matches) return;
    const calendar = host.querySelector('.teacher-my-classes-calendar');
    if (!calendar) return;
    const calendarHeight = calendar.getBoundingClientRect().height;
    const contentHeight = detail.getBoundingClientRect().height;
    if (calendarHeight > contentHeight + 2) {
      detail.style.minHeight = `${Math.ceil(calendarHeight)}px`;
      detail.style.boxSizing = 'border-box';
    }
  }

  window.addEventListener('resize', balanceDetailShell, { passive: true });

  function beginEdit() {
    const item = selectedClass();
    if (!item) return;
    draft = (item.schedules || []).map((schedule) => ({ classScheduleId: schedule.classScheduleId, day: schedule.day, startTime: schedule.startTime, endTime: schedule.endTime, room: schedule.roomValue || '' }));
    baseline = scheduleCopy(draft);
    editing = true;
    render();
  }

  host.addEventListener('click', async (event) => {
    const meeting = event.target.closest('[data-meeting-class]');
    const targetClass = event.target.closest('[data-meeting-draft]');
    const slot = event.target.closest('[data-add-slot]');
    if (meeting && !editing) {
      selectedId = Number(meeting.dataset.meetingClass);
      render();
      return;
    }
    if (targetClass && editing) {
      const editor = detail.querySelector(`[data-editor-meeting="${targetClass.dataset.meetingDraft}"]`);
      editor?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      editor?.querySelector('input')?.focus();
      return;
    }
    if (slot && editing) {
      const start = slot.dataset.start;
      const hour = Number(start.slice(0, 2));
      const endHour = Math.min(23, hour + 2);
      draft.push({ classScheduleId: null, day: slot.dataset.day, startTime: start, endTime: `${String(endHour).padStart(2, '0')}:00`, room: '' });
      render();
      detail.querySelector(`[data-editor-meeting="${draft.length - 1}"]`)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      detail.querySelector(`[data-editor-meeting="${draft.length - 1}"] input`)?.focus();
      return;
    }
    if (event.target.closest('[data-edit-schedule]')) { beginEdit(); return; }
    if (event.target.closest('[data-clear-selection]')) { selectedId = 0; render(); return; }
    if (event.target.closest('[data-add-meeting]')) { draft.push({ classScheduleId: null, day: host.dataset.today && days.includes(host.dataset.today) ? host.dataset.today : 'Monday', startTime: '09:00', endTime: '11:00', room: '' }); render(); detail.querySelector(`[data-editor-meeting="${draft.length - 1}"] input`)?.focus(); return; }
    const dayPick = event.target.closest('[data-day-pick]');
    if (dayPick) { const editor = dayPick.closest('[data-editor-meeting]'); if (editor) draft[Number(editor.dataset.editorMeeting)].day = dayPick.dataset.dayPick; render(); return; }
    const remove = event.target.closest('[data-remove-meeting]');
    if (remove) { const editor = remove.closest('[data-editor-meeting]'); draft.splice(Number(editor.dataset.editorMeeting), 1); render(); return; }
    if (event.target.closest('[data-cancel-edit]')) { editing = false; draft = []; statusMessage = ''; render(); return; }
    if (event.target.closest('[data-save-schedule]')) {
      const button = event.target.closest('[data-save-schedule]');
      button.disabled = true; button.textContent = 'Saving…';
      const body = new URLSearchParams({ csrf_token: host.dataset.csrf, operational_class_id: String(selectedId), schedules: JSON.stringify(draft) });
      try {
        const response = await fetch(host.dataset.saveUrl, { method: 'POST', body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const fallbackMessage = 'The schedule could not be saved. No changes were applied.';
        const contentType = response.headers.get('content-type') || '';
        let result = null;
        if (contentType.toLowerCase().includes('application/json')) {
          try { result = await response.json(); } catch (_) { result = null; }
        }
        if (!result || !response.ok || result.success !== true) {
          throw new Error(typeof result?.message === 'string' && result.message.trim() ? result.message : fallbackMessage);
        }
        window.location.reload();
      } catch (error) {
        statusMessage = error instanceof Error && error.message ? error.message : 'The schedule could not be saved. No changes were applied.';
        button.disabled = false; render();
        const summary = detail.querySelector('.teacher-my-classes-edit-summary');
        if (summary) { summary.textContent = statusMessage; summary.classList.add('is-invalid'); }
      }
    }
  });

  host.addEventListener('input', (event) => {
    const field = event.target.closest('[data-meeting-field]');
    if (!field) return;
    const editor = field.closest('[data-editor-meeting]');
    const meeting = draft[Number(editor.dataset.editorMeeting)];
    meeting[field.dataset.meetingField] = field.value;
    renderCalendar();
    renderDetail();
  });

  render();
})();
