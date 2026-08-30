// --- Generate a unique user ID ---
function generateUserId() {
  return 'user-' + Date.now();
}

document.addEventListener('DOMContentLoaded', () => {
  // --- Hide "Copy Link" buttons in single card view ---
  const params = new URLSearchParams(window.location.search);
  if (params.has('card')) {
    const cardId = params.get('card');
    document.querySelectorAll('.card').forEach(card => {
      if (card.getAttribute('data-card') !== cardId) {
        card.style.display = 'none';
      }
    });
    document.querySelectorAll('.share-link-btn').forEach(btn => {
      btn.style.display = 'none';
    });
  }

  // --- View Details Modal ---
  document.querySelectorAll('.view-details').forEach(button => {
    button.addEventListener('click', e => {
      const card = e.target.closest('.card');
      const pdfSrc = card.getAttribute('data-pdf');
      const formUrl = card.getAttribute('data-form');
      const title = card.getAttribute('data-title');

      document.getElementById('modal-title').textContent = title;
      document.getElementById('pdf-viewer').src = pdfSrc;

      const modal = document.getElementById('modal');
      modal.classList.remove('hidden');

      const checkbox = document.getElementById('agree-checkbox');
      const applyNowBtn = document.getElementById('apply-now');
      checkbox.checked = false;
      applyNowBtn.disabled = true;

      const toggleApplyNowBtn = () => {
        applyNowBtn.disabled = !checkbox.checked;
      };
      checkbox.addEventListener('change', toggleApplyNowBtn);

      document.getElementById('close-modal').onclick = () => {
        modal.classList.add('hidden');
        document.getElementById('pdf-viewer').src = '';
      };
    });
  });

  // --- Copy Share Link ---
  document.querySelectorAll('.share-link-btn').forEach(button => {
    button.addEventListener('click', e => {
      const card = e.target.closest('.card');
      const cardId = card.getAttribute('data-card');
      let path = window.location.pathname;
      if (!path.endsWith('index.php')) {
        path = path.endsWith('/') ? path + 'index.php' : path + '/index.php';
      }
      const url = `${window.location.origin}${path}?card=${cardId}`;
      navigator.clipboard.writeText(url)
        .then(() => alert(`Link copied to clipboard:\n${url}`))
        .catch(err => console.error('Failed to copy: ', err));
    });
  });

  // --- Multi-step Save & Next (no progress bar needed here) ---
  document.querySelectorAll('.next-btn').forEach(button => {
    button.addEventListener('click', () => {
      const currentStep = button.closest('.form-step');
      // ✅ Force "Canada" as destination_loan if empty and master's is selected
const mastersValue = document.querySelector('[name="masters_program"]')?.value?.trim();
const destinationCheckboxes = document.querySelectorAll('[name="destination_loan[]"]');

if (mastersValue && destinationCheckboxes.length > 0) {
  const anyChecked = Array.from(destinationCheckboxes).some(cb => cb.checked);
  if (!anyChecked) {
    const hiddenCanada = document.createElement('input');
    hiddenCanada.type = 'checkbox';
    hiddenCanada.name = 'destination_loan[]';
    hiddenCanada.value = 'Canada';
    hiddenCanada.checked = true;
    hiddenCanada.required = true; // ✅ Ensure it's part of validation check
    hiddenCanada.style.display = 'none';
   currentStep.appendChild(hiddenCanada);
    console.log('✅ Injected hidden checkbox for destination_loan = Canada');
  }
}
      if (!validateFields(currentStep)) return;

      const nextStepId = button.getAttribute('data-next');
      const formData = prepareFormData(currentStep.id);
      const xhr = new XMLHttpRequest();
      const saveUrl = getSaveUrl();

      xhr.open('POST', saveUrl, true);
      xhr.onload = () => {
        if (xhr.status === 200) {
          try {
            const response = JSON.parse(xhr.responseText);
            if (response.status === 'success') {
              currentStep.classList.remove('active');
              document.getElementById('step' + nextStepId).classList.add('active');
              window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
              alert('Error saving data: ' + (response.message || 'Unknown error'));
            }
          } catch (e) {
            alert('Error parsing response: ' + e.message);
          }
        } else {
          alert('Error submitting: ' + xhr.responseText);
        }
      };
      xhr.send(formData);
    });
  });

  // --- Multi-step Previous ---
  document.querySelectorAll('.prev-btn').forEach(button => {
    button.addEventListener('click', () => {
      const currentStep = button.closest('.form-step');
      const prevStepId = button.getAttribute('data-prev');
      currentStep.classList.remove('active');
      document.getElementById('step' + prevStepId).classList.add('active');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  // --- Final Submit (only place using the progress bar) ---
const submitBtn = document.querySelector('.submit-btn');
if (submitBtn) {
  submitBtn.addEventListener('click', (e) => {
    e.preventDefault();
    console.log('✅ Submit button clicked');

    const currentStep = submitBtn.closest('.form-step');
    if (!currentStep) {
      console.error('❌ Cannot find current step.');
      return;
    }

    if (!validateFields(currentStep)) {
      console.warn('❌ Validation failed.');
      return;
    }

    console.log('✅ Validation passed. Preparing form data...');
    const formData = prepareFormData(currentStep.id);

    const progressWrapper = document.getElementById('progress-wrapper');
    const progressBar = document.getElementById('progress-bar');

    if (!progressWrapper || !progressBar) {
      console.warn('⚠️ Progress elements not found.');
    } else {
      console.log('✅ Showing progress bar...');
        // Force visibility and repaint
progressWrapper.style.display = 'block';
progressWrapper.style.visibility = 'visible';
progressWrapper.style.height = 'auto'; // Just in case height is being suppressed
progressWrapper.offsetHeight; // Force reflow
progressBar.style.width = '0%';
progressBar.textContent = '0%';
console.log('✅ Progress bar should now be visible');
    }

   const xhr = new XMLHttpRequest();
const saveUrl = getSaveUrl();
console.log(`📡 Submitting to URL: ${saveUrl}`);
xhr.open('POST', saveUrl, true);

// Hold percent value outside for smooth final animation
let uploadPercent = 0;

xhr.upload.onprogress = (e) => {
  if (e.lengthComputable && progressBar) {
    uploadPercent = (e.loaded / e.total) * 100;
    if (uploadPercent > 95) uploadPercent = 95; // Cap progress at 95% before server confirms
    progressBar.style.width = uploadPercent + '%';
    progressBar.textContent = Math.round(uploadPercent) + '%';
  }
};

xhr.onload = () => {
  console.log('📥 Server response received.');
  if (xhr.status === 200) {
    try {
      const response = JSON.parse(xhr.responseText);
      console.log('✅ Parsed response:', response);
      if (response.status === 'success') {
        // Smooth final fill from current (95%) to 100%
        setTimeout(() => {
          progressBar.style.width = '100%';
          progressBar.textContent = '100%';
        }, 300);

        setTimeout(() => {
          alert('✅ Application submitted successfully! Your Application ID: ' + response.user_id);
          window.location.href = `index.php?id=${response.user_id}`;
        }, 800); // Slight delay for smoother UX
      } else {
        alert('❌ Submission error: ' + (response.message || 'Unknown error.'));
      }
    } catch (err) {
      console.error('❌ Failed to parse response JSON.', err);
      alert('❌ Error parsing server response.');
    }
  } else {
    console.error('❌ Server returned error:', xhr.responseText);
    alert('❌ Error submitting final step: ' + xhr.responseText);
  }
};

xhr.onerror = () => {
  console.error('❌ Network error during upload.');
  alert('❌ Upload failed due to network error.');
};
let simulated = 0;
const simulateProgress = setInterval(() => {
  simulated += 5;
  if (simulated <= 95) {
    progressBar.style.width = simulated + '%';
    progressBar.textContent = simulated + '%';
  } else {
    clearInterval(simulateProgress);
  }
}, 100);

xhr.send(formData);

  });
}

  // --- Helper functions ---
  function getSaveUrl() {
    const form = document.getElementById('applicationForm');
    return form.getAttribute('data-save') || 'save_form-canada.php';
  }
 // === Handle "Other" Destination logic ===
const otherField = document.querySelector('[name="other_destination_loan"]');
const destinationCheckboxes = document.querySelectorAll('[name="destination_loan[]"]');

function updateOtherLoanFieldRequirement() {
  if (!otherField) return; // ✅ Exit if field is not in the DOM

  let otherSelected = false;
  destinationCheckboxes.forEach(cb => {
    if (cb.checked && cb.value.toLowerCase().includes('other')) {
      otherSelected = true;
    }
  });

  if (otherSelected) {
    otherField.setAttribute('required', 'required');
    otherField.closest('.form-group')?.style?.setProperty('display', '');
  } else {
    otherField.removeAttribute('required');
    otherField.value = '';
    otherField.closest('.form-group')?.style?.setProperty('display', 'none');
  }
}

// Attach listeners safely
if (otherField) {
  destinationCheckboxes.forEach(cb => cb.addEventListener('change', updateOtherLoanFieldRequirement));
  updateOtherLoanFieldRequirement(); // Run once on load
}


// Run on load
updateOtherLoanFieldRequirement();

 function validateFields(stepElement) {
  const requiredFields = stepElement.querySelectorAll('[required]');
  let isValid = true;

  const bachelorValue = document.querySelector('[name="bachelor_program"]')?.value?.trim();
  const mastersValue = document.querySelector('[name="masters_program"]')?.value?.trim();
  const phdValue = document.querySelector('[name="phd_program"]')?.value?.trim();

  const bachelorSelected = !!bachelorValue;
  const mastersSelected = !!mastersValue;
  const phdSelected = !!phdValue;

  const loanFields = [
    'paying_tuition_fees',
    'paying_cost_living',
    'paying_travel_expenses',
    'destination_loan',
  ];

  console.log('📋 Running validation on step:', stepElement.id);
  console.log('🎓 Program Selected:', {
    bachelor: bachelorValue,
    master: mastersValue,
    phd: phdValue
  });

  requiredFields.forEach(field => {
    const fieldName = field.getAttribute('name');

    // ⛔ Skip Step 3 loan-related fields if not master's program
    if (!mastersSelected && loanFields.includes(fieldName)) {
      console.log(`⏭️ Skipping loan field (not required): ${fieldName}`);
      removeHighlight(field);
      return;
    }

    // Skip hidden elements
        // ✅ Skip fields not currently visible
    if (!field.offsetParent) {
      console.log(`⏭️ Skipping hidden field: ${fieldName}`);
      removeHighlight(field);
      return;
    }

    // ✅ Handle radio groups
    if (field.type === 'radio') {
      const isChecked = stepElement.querySelector(`[name="${field.name}"]:checked`);
      if (!isChecked) {
        console.warn(`❌ Missing required radio field: ${fieldName}`);
        isValid = false;
        highlightField(field);
      } else {
        removeHighlight(field);
      }

    // ✅ Handle checkbox groups (e.g., destination_loan[])
    } else if (field.type === 'checkbox' && field.name.endsWith('[]')) {
      const group = stepElement.querySelectorAll(`[name="${field.name}"]`);
      const isAnyChecked = [...group].some(cb => cb.checked);
      if (!isAnyChecked) {
        console.warn(`❌ Missing required checkbox group: ${fieldName}`);
        isValid = false;
        highlightField(field);
      } else {
        removeHighlight(field);
      }

    // ✅ Handle regular input/select/textarea fields
    } else if (!field.value || field.value.trim() === '') {
      console.warn(`❌ Missing required field: ${fieldName}`);
      isValid = false;
      highlightField(field);
    } else {
      removeHighlight(field);
    }

  });

  if (!isValid) alert('Please fill in all required fields.');
  return isValid;
}




  function prepareFormData(stepId) {
    const form = document.getElementById('applicationForm');
    const formData = new FormData(form);
    formData.append('step', stepId);
    return formData;
  }

//   function prepareFormData(stepId) {
//   const form = document.getElementById('applicationForm');
//   const formData = new FormData(form);
//   formData.append('step', stepId.replace('step', ''));  // ✅ FIXED
//   return formData;
// }


  function highlightField(field) {
    field.style.border = '2px solid red';
  }

  function removeHighlight(field) {
    field.style.border = '';
  }
});
