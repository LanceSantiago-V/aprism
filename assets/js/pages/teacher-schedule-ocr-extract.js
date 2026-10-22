/* Browser-only draft extraction. It does not validate or save a class. */
(() => {
  'use strict';

  const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  const dayPattern = /\b(mon(?:day)?|tue(?:s(?:day)?)?|wed(?:nesday)?|thu(?:rs(?:day)?)?|fri(?:day)?|sat(?:urday)?)\b/i;
  const sectionPattern = /\b(?:[A-Z]{2,10}[.-]?\d{1,2}(?:[.-]\d{1,2})?[A-Z]?|[A-Z]{2,10}-\d{1,2}[A-Z])\b/i;
  const timePattern = /\b(\d{1,2}:\d{2})\s*(AM|PM)?\s*(?:-|–|—|to)\s*(\d{1,2}:\d{2})\s*(AM|PM)?\b/i;
  const skipPattern = /\b(break|lunch|overload|teaching hours|reporting time|total|chair|faculty name|faculty schedule|status)\b/i;

  function dayFor(value) {
    const match = String(value || '').match(dayPattern);
    if (!match) return '';
    const short = match[1].slice(0, 3).toLowerCase();
    return days.find((day) => day.slice(0, 3).toLowerCase() === short) || '';
  }

  function clock(value, suffix) {
    const match = String(value || '').match(/^(\d{1,2}):(\d{2})$/);
    if (!match) return '';
    let hour = Number(match[1]);
    const minute = Number(match[2]);
    if (hour > 23 || minute > 59) return '';
    if (suffix) {
      if (hour < 1 || hour > 12) return '';
      hour = (hour % 12) + (suffix.toUpperCase() === 'PM' ? 12 : 0);
    }
    return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
  }

  function timePair(value) {
    const match = String(value || '').match(timePattern);
    if (!match) return { startTime: '', endTime: '' };
    // A lone AM/PM suffix applies to both ends only when both times are on that side of noon.
    const firstSuffix = match[2] || match[4] || '';
    const lastSuffix = match[4] || match[2] || '';
    const startTime = clock(match[1], firstSuffix);
    const endTime = clock(match[3], lastSuffix);
    return {
      startTime: startTime && endTime && startTime < endTime ? startTime : '',
      endTime: startTime && endTime && startTime < endTime ? endTime : ''
    };
  }

  function roomFor(value) {
    const match = String(value || '').match(/\b(room|rm\.?|lab|comlab)\s*[:#-]?\s*([A-Z0-9][A-Z0-9 .-]{0,22})/i);
    if (!match) return '';
    const valueOnly = match[2].trim().replace(/[.,;]+$/, '');
    return /^(room|rm\.?)$/i.test(match[1]) ? valueOnly : `${match[1]} ${valueOnly}`;
  }

  function cleanSubject(value) {
    return String(value || '')
      .replace(dayPattern, ' ')
      .replace(timePattern, ' ')
      .replace(sectionPattern, ' ')
      .replace(/\b(?:room|rm\.?|lab|comlab)\s*[:#-]?\s*[A-Z0-9][A-Z0-9 .-]{0,22}/i, ' ')
      .replace(/[|,;]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function draft(values) {
    const result = {
      subject: String(values.subject || '').trim(),
      section: String(values.section || '').trim(),
      day: dayFor(values.day),
      startTime: String(values.startTime || '').trim(),
      endTime: String(values.endTime || '').trim(),
      room: String(values.room || '').trim(),
      evidence: String(values.evidence || '').trim()
    };
    result.uncertain = !result.subject || !result.section || !result.day ||
      !result.startTime || !result.endTime;
    return result;
  }

  function fromText(text) {
    const output = [];
    for (const sourceLine of String(text || '').split(/\r?\n/)) {
      const line = sourceLine.replace(/\s+/g, ' ').trim();
      if (!line || skipPattern.test(line)) continue;
      const day = dayFor(line);
      const times = timePair(line);
      const withoutRoom = line.replace(/\b(?:room|rm\.?|lab|comlab)\s*[:#-]?\s*[A-Z0-9][A-Z0-9 .-]{0,22}/i, ' ');
      const section = withoutRoom.match(sectionPattern)?.[0] || '';
      if (!day || (!times.startTime && !section)) continue;
      const subject = cleanSubject(line);
      if (!subject && !section) continue;
      output.push(draft({ subject, section, day, ...times, room: roomFor(line), evidence: line }));
    }
    return output;
  }

  function wordsFromTsv(tsv) {
    const rows = String(tsv || '').split(/\r?\n/);
    const header = rows.shift()?.split('\t') || [];
    const index = Object.fromEntries(header.map((name, position) => [name, position]));
    if (index.text === undefined || index.left === undefined) return [];
    return rows.map((row) => {
      const cells = row.split('\t');
      const value = (name) => cells[index[name]] || '';
      return {
        text: value('text').trim(), x: Number(value('left')), y: Number(value('top')),
        width: Number(value('width')), height: Number(value('height')),
        confidence: Number(value('conf')), group: [value('page_num'), value('block_num'), value('par_num'), value('line_num')].join(':')
      };
    }).filter((word) => word.text && Number.isFinite(word.x) && Number.isFinite(word.y) && word.confidence >= 10);
  }

  function linesFromWords(words) {
    const groups = new Map();
    for (const word of words) {
      const key = word.group || String(Math.round(word.y / 9));
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push(word);
    }
    return [...groups.values()].map((group) => {
      group.sort((a, b) => a.x - b.x);
      return {
        text: group.map((word) => word.text).join(' ').trim(),
        x: Math.min(...group.map((word) => word.x)),
        y: Math.min(...group.map((word) => word.y)),
        width: Math.max(...group.map((word) => word.x + word.width)) - Math.min(...group.map((word) => word.x))
      };
    }).sort((a, b) => a.y - b.y || a.x - b.x);
  }

  function fromGridWords(words) {
    const lines = linesFromWords(words);
    const headings = lines.filter((line) => /^(mon|tue|wed|thu|fri|sat)(?:day)?$/i.test(line.text))
      .map((line) => ({ day: dayFor(line.text), x: line.x + line.width / 2, y: line.y }))
      .sort((a, b) => a.x - b.x);
    if (headings.length < 2) return [];
    const headerY = Math.max(...headings.map((heading) => heading.y));
    const dayAt = (x) => {
      let closest = headings[0];
      for (const heading of headings) {
        if (Math.abs(heading.x - x) < Math.abs(closest.x - x)) closest = heading;
      }
      const spacing = headings.length > 1 ? Math.abs(headings[1].x - headings[0].x) : Infinity;
      return Math.abs(closest.x - x) <= spacing * 0.58 ? closest.day : '';
    };
    const output = [];
    for (const anchor of lines) {
      if (anchor.y <= headerY || skipPattern.test(anchor.text) || roomFor(anchor.text)) continue;
      const section = anchor.text.match(sectionPattern)?.[0] || '';
      const day = dayAt(anchor.x + anchor.width / 2);
      if (!section || !day) continue;
      const nearby = lines.filter((line) => line !== anchor && dayAt(line.x + line.width / 2) === day &&
        line.y < anchor.y && line.y >= anchor.y - 65 && !skipPattern.test(line.text) &&
        !sectionPattern.test(line.text) && !timePattern.test(line.text));
      const subject = nearby.slice(-3).map((line) => line.text).join(' ').trim();
      const below = lines.find((line) => line.y > anchor.y && line.y <= anchor.y + 40 &&
        dayAt(line.x + line.width / 2) === day && roomFor(line.text));
      output.push(draft({ subject, section, day, room: below ? roomFor(below.text) : '',
        evidence: `${subject} ${anchor.text} ${below?.text || ''}`.trim() }));
    }
    return output;
  }

  function uniqueDrafts(items) {
    const seen = new Set();
    return items.filter((item) => {
      const key = [item.subject, item.section, item.day, item.startTime, item.endTime, item.room]
        .map((value) => value.toLowerCase()).join('|');
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    });
  }

  window.AprismScheduleOcrExtract = { fromText, fromGridWords, wordsFromTsv, uniqueDrafts, draft };
})();
