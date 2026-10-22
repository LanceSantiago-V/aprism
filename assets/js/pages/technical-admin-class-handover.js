(() => {
  const form = document.getElementById('classHandoverForm');
  if (!form) return;

  const classSelect = document.getElementById('handoverClass');
  const teacherSelect = document.getElementById('handoverTeacher');
  const ownerInput = document.getElementById('handoverExpectedTeacher');
  const currentTeacher = document.getElementById('handoverCurrentTeacher');
  const currentTeacherName = document.getElementById('handoverCurrentTeacherName');
  const noReplacement = document.getElementById('handoverNoReplacement');
  const categoryInput = document.getElementById('handoverReasonCategory');
  const categoryGroup = document.getElementById('handoverReasonOptions');
  const categoryButtons = Array.from(form.querySelectorAll('[data-handover-category]'));
  const reasonWrap = document.getElementById('handoverSpecificReasonWrap');
  const reasonSelect = document.getElementById('handoverSpecificReason');
  const detailsWrap = document.getElementById('handoverReasonDetailsWrap');
  const details = document.getElementById('handoverReasonDetails');
  const detailsRequiredMark = document.getElementById('handoverReasonDetailsRequired');
  const detailsOptionalLabel = document.getElementById('handoverReasonDetailsOptional');
  const detailsToggle = document.getElementById('handoverDetailsToggle');
  const showDetailsInput = document.getElementById('handoverShowReasonDetails');
  const confirmation = document.getElementById('handoverConfirmation');
  const reviewed = form.querySelector('[name="confirmation_intent"]');
  const submitButton = document.getElementById('handoverSubmit');
  const review = document.getElementById('handoverReview');
  const reviewPrompt = document.getElementById('handoverReviewPrompt');
  const reviewClass = document.getElementById('handoverReviewClass');
  const reviewOriginal = document.getElementById('handoverReviewOriginal');
  const reviewReplacement = document.getElementById('handoverReviewReplacement');
  const reviewReason = document.getElementById('handoverReviewReason');

  const categories = {
    personnel: { label: 'Personnel change', codes: ['resignation', 'leave_of_absence'] },
    assignment: { label: 'Teaching assignment', codes: ['change_in_teaching_assignment', 'schedule_reassignment'] },
    other: { label: 'Other', codes: [] },
  };
  const reasonLabels = {
    resignation: 'Resignation',
    leave_of_absence: 'Leave of Absence',
    change_in_teaching_assignment: 'Change in Teaching Assignment',
    schedule_reassignment: 'Schedule Reassignment',
  };

  const updateReasonControls = () => {
    const category = categoryInput.value;
    const selectedCategory = categories[category];
    categoryButtons.forEach((button) => {
      const selected = button.dataset.handoverCategory === category;
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });

    const hasSpecificReasons = Boolean(selectedCategory?.codes.length);
    reasonWrap.hidden = !hasSpecificReasons;
    reasonSelect.disabled = !hasSpecificReasons;
    reasonSelect.required = hasSpecificReasons;
    Array.from(reasonSelect.options).forEach((option) => {
      if (!option.value) return;
      const available = selectedCategory?.codes.includes(option.value) || false;
      option.hidden = !available;
      option.disabled = !available;
    });
    if (!hasSpecificReasons || !selectedCategory.codes.includes(reasonSelect.value)) {
      reasonSelect.value = '';
    }

    const isOther = category === 'other';
    detailsToggle.hidden = !selectedCategory || isOther;
    details.required = isOther;
    details.setAttribute('aria-required', isOther ? 'true' : 'false');
    detailsRequiredMark.hidden = !isOther;
    detailsOptionalLabel.hidden = isOther;
    if (isOther) {
      detailsWrap.hidden = false;
      detailsToggle.setAttribute('aria-expanded', 'true');
    } else if (!showDetailsInput.value || showDetailsInput.value === '0') {
      detailsWrap.hidden = true;
      detailsToggle.setAttribute('aria-expanded', 'false');
    }
    details.disabled = detailsWrap.hidden;
  };

  const updateReview = () => {
    const selectedClass = classSelect.selectedOptions[0];
    const ownerId = selectedClass?.dataset.ownerId || '';
    ownerInput.value = ownerId;
    currentTeacher.hidden = !ownerId;
    currentTeacherName.textContent = ownerId
      ? `${selectedClass.dataset.ownerName} · Employee ID ${selectedClass.dataset.ownerEmployeeNumber}`
      : '';

    let availableReplacementCount = 0;
    Array.from(teacherSelect.options).forEach((option) => {
      if (!option.value) return;
      const isCurrentTeacher = option.value === ownerId;
      option.disabled = isCurrentTeacher;
      if (!isCurrentTeacher) availableReplacementCount += 1;
      if (isCurrentTeacher && option.selected) teacherSelect.value = '';
    });

    const hasSelectedClass = Boolean(ownerId);
    const hasReplacement = Boolean(teacherSelect.value) && teacherSelect.value !== ownerId;
    noReplacement.hidden = !hasSelectedClass || availableReplacementCount > 0;
    teacherSelect.disabled = hasSelectedClass && availableReplacementCount === 0;

    const category = categories[categoryInput.value];
    const selectedReason = reasonSelect.value;
    const otherDetails = detailsWrap.hidden ? '' : details.value.trim();
    const reasonIsComplete = Boolean(category) && (categoryInput.value === 'other'
      ? otherDetails.length > 0
      : Boolean(category.codes.includes(selectedReason)));
    const ready = hasSelectedClass && hasReplacement && reasonIsComplete;

    if (ready) {
      reviewClass.textContent = selectedClass.dataset.classContext || '';
      reviewOriginal.textContent = currentTeacherName.textContent;
      reviewReplacement.textContent = teacherSelect.selectedOptions[0].textContent.trim();
      reviewReason.textContent = categoryInput.value === 'other'
        ? `Other: ${otherDetails}`
        : `${category.label}: ${reasonLabels[selectedReason]}${otherDetails ? ` — ${otherDetails}` : ''}`;
      review.hidden = false;
      reviewPrompt.hidden = true;
      confirmation.hidden = false;
      submitButton.disabled = !reviewed.checked;
    } else {
      review.hidden = true;
      reviewPrompt.hidden = false;
      confirmation.hidden = true;
      submitButton.disabled = true;
      if (!hasSelectedClass) reviewPrompt.textContent = 'Select a class, replacement Teacher, and reason to review the transfer.';
      else if (!hasReplacement) reviewPrompt.textContent = availableReplacementCount === 0
        ? 'No eligible replacement Teacher is available for this class.'
        : 'Select a replacement Teacher and reason to review the transfer.';
      else reviewPrompt.textContent = 'Complete the reason details to review the transfer.';
    }
  };

  categoryButtons.forEach((button) => button.addEventListener('click', () => {
    if (categoryInput.value !== button.dataset.handoverCategory) details.value = '';
    categoryInput.value = button.dataset.handoverCategory;
    showDetailsInput.value = categoryInput.value === 'other' ? '1' : '0';
    reviewed.checked = false;
    updateReasonControls();
    updateReview();
  }));

  detailsToggle.addEventListener('click', () => {
    const opening = detailsWrap.hidden;
    detailsWrap.hidden = !opening;
    detailsToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
    showDetailsInput.value = opening ? '1' : '0';
    details.disabled = !opening;
    if (!opening) details.value = '';
    reviewed.checked = false;
    updateReview();
    if (opening) details.focus();
  });

  [classSelect, teacherSelect, reasonSelect].forEach((control) => control.addEventListener('change', () => {
    reviewed.checked = false;
    updateReasonControls();
    updateReview();
  }));
  reviewed.addEventListener('change', updateReview);
  details.addEventListener('input', () => {
    reviewed.checked = false;
    updateReview();
  });

  form.addEventListener('submit', (event) => {
    if (!reviewed.checked || submitButton.disabled) {
      event.preventDefault();
      return;
    }
    submitButton.disabled = true;
    submitButton.setAttribute('aria-busy', 'true');
    submitButton.textContent = 'Submitting handover…';
  });

  updateReasonControls();
  updateReview();
})();
