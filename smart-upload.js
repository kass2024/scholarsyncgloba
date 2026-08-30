// smart-upload.js

function saveStep(stepId) {
  const form = document.getElementById('creditForm');
  const formData = new FormData(form);
  formData.append('step', stepId);

  const stepFields = document.querySelectorAll(`#${stepId} input, #${stepId} select, #${stepId} textarea`);
  stepFields.forEach(field => {
    if (field.name && field.type !== 'submit') {
      if (field.type === 'file' && field.files.length > 0) {
        formData.append(field.name, field.files[0]);
      } else if (field.type === 'checkbox') {
        const checkboxes = [...document.querySelectorAll(`[name='${field.name}']:checked`)];
        checkboxes.forEach(cb => formData.append(field.name + '[]', cb.value));
      } else {
        formData.set(field.name, field.value);
      }
    }
  });

  fetch(form.getAttribute('data-save'), {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      console.log('✅ Step saved:', stepId);

      // Extract step number from 'step1', 'step2', etc.
      const stepNum = parseInt(stepId.replace('step', ''));
      showStep(stepNum + 1); // Move to next step
    } else {
      alert('❌ Save failed: ' + data.message);
    }
  })
  .catch(err => {
    alert('❌ AJAX error: ' + err.message);
  });
}

function retrieveStepData(userId) {
  fetch(`get_credit_data.php?user_id=${encodeURIComponent(userId)}`)
    .then(res => res.json())
    .then(data => {
      if (data.status !== 'success') return;

      const form = document.getElementById('creditForm');
      const values = data.values;

      console.log('✅ Retrieved values:', values);

      Object.entries(values).forEach(([key, val]) => {
        if (val === null || val === '') return;

        // ✅ Handle JSON checkbox arrays
        if (key === 'education_levels' || key === 'certification_levels') {
          const checkboxName = key === 'education_levels' ? 'edu_level[]' : 'cert_level[]';

          let parsed = [];
          try {
            parsed = JSON.parse(val);
          } catch (e) {
            console.warn(`❌ Failed to parse ${key}:`, val);
          }

          if (Array.isArray(parsed)) {
            parsed.forEach(v => {
              const checkbox = [...form.querySelectorAll(`input[name="${checkboxName}"]`)]
                .find(cb => cb.value.trim().toLowerCase() === v.trim().toLowerCase());

              if (checkbox) {
                checkbox.checked = true;
              } else {
                console.warn(`⚠️ No matching checkbox found for "${v}" in ${checkboxName}`);
              }
            });
          }

          return; // Continue to next field
        }

        // ✅ Standard fields
        const input = form.querySelector(`[name="${key}"]`);
        if (input) input.value = val;
      });

      // ✅ Re-enable current step fields if previously disabled
      const activeStep = document.querySelector('.form-step.active');
      if (activeStep) {
        activeStep.querySelectorAll('input, select, textarea').forEach(f => f.disabled = false);
      }

      console.log('✅ Form auto-filled and fields re-enabled for:', userId);
    })
    .catch(err => {
      console.error('❌ Retrieval error:', err);
    });
}
