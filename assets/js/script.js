document.getElementById('transcriptForm').addEventListener('submit', async function (e) {
  e.preventDefault();

  const form = e.target;
  const formData = new FormData(form);

  document.getElementById('loading').classList.remove('hidden');
  document.getElementById('error').classList.add('hidden');
  document.getElementById('result').classList.add('hidden');

  try {
    const res = await fetch('../extract/extract_transcript.php', {
      method: 'POST',
      body: formData
    });

    const data = await res.json();

    document.getElementById('loading').classList.add('hidden');

    if (data.status !== 'success') {
      document.getElementById('error').innerText = data.message || 'Extraction failed';
      document.getElementById('error').classList.remove('hidden');
      return;
    }

    const info = data.data;

    // Build full name
    const fullName = `${info.student.name} ${info.student.surname}`;

    document.getElementById('studentName').innerText = fullName;
    document.getElementById('regNumber').innerText = info.student.registration_number;
    document.getElementById('program').innerText = info.student.program;
    document.getElementById('academicYear').innerText = info.student.academic_year;
    document.getElementById('courseCount').innerText = info.courses.length;
    document.getElementById('verdict').innerText = info.verdict;

    // Store for next step (templates)
    sessionStorage.setItem('extractedTranscript', JSON.stringify(info));

    document.getElementById('result').classList.remove('hidden');

  } catch (err) {
    document.getElementById('loading').classList.add('hidden');
    document.getElementById('error').innerText = 'Server error';
    document.getElementById('error').classList.remove('hidden');
  }
});

document.getElementById('continueBtn').onclick = () => {
  // Next page will use extracted data
  window.location.href = 'generate.html';
};
