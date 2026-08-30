<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>UOBS Application Form (Foreign Applicants)</title>
<style>
:root{
  --ink:#000;
  --bg:#fff;
  --w:920px;          /* page width close to screenshot */
  --b:2px;            /* heavy border like printed form */
}
*{box-sizing:border-box}
html,body{margin:0;background:var(--bg);color:#000;font:14px/1.4 Arial,Helvetica,system-ui}

/* page container */
.page{max-width:var(--w);margin:0 auto;padding:0 12px}

/* header (two blocks + centered emblem) */
.header{border-bottom:var(--b) solid var(--ink);padding:10px 0 6px;}
.header-grid{display:grid;grid-template-columns:1fr 110px 1fr;align-items:center;gap:10px}
.header .block{border:1px solid var(--ink);padding:8px 10px 6px;text-transform:uppercase;font-weight:700;font-size:12px;line-height:1.25}
.header .block small{display:block;font-weight:400;text-transform:none;font-size:11px;margin-top:4px}
.header .crest{display:flex;align-items:center;justify-content:center}
.header .crest img{height:64px;display:block;margin:auto}
.header .meta{text-align:center;font-size:12px;margin:8px 0 0}

/* title */
.title{text-align:center;margin:8px 0 8px}
.title h1{margin:0;font-size:24px;letter-spacing:.3px}
.title small{display:block;font-size:12px}

/* section header bars */
.section{border:var(--b) solid var(--ink);margin:14px 0 10px}
.section .bar{background:#000;color:#fff;font-weight:800;letter-spacing:.3px;padding:6px 10px;font-size:13px}

/* grid mimicking the printed table */
.table{display:grid;border-top:var(--b) solid var(--ink)}
.row{display:grid;grid-template-columns:220px 1fr;border-bottom:var(--b) solid var(--ink)}
.cell-h{background:#f2f2f2;border-right:var(--b) solid var(--ink);padding:8px 10px;font-weight:700}
.cell{padding:6px 8px;display:flex;align-items:center;gap:10px}
.row.split-3{grid-template-columns:220px 1fr 220px 1fr}
.row.split-2a{grid-template-columns:220px 1fr 1fr}
.cell + .cell{border-left:var(--b) solid var(--ink)}
.subbar{background:#000;color:#fff;font-weight:800;padding:6px 10px;border-top:var(--b) solid var(--ink)}

/* inputs */
input[type=text],input[type=email],input[type=date],textarea,select{
  width:100%;border:1px solid #000;padding:6px 8px;border-radius:0;font-size:14px
}
textarea{min-height:64px;resize:vertical}
.checkset{display:flex;gap:16px;flex-wrap:wrap}
.checkset label{display:inline-flex;align-items:center;gap:6px;font-weight:700}
.gender-right{justify-content:flex-end}

/* footer declaration grid */
.decl{display:grid;grid-template-columns:1fr 1fr 1fr;border-top:var(--b) solid var(--ink)}
.decl > div{border-left:var(--b) solid var(--ink);padding:12px}
.decl > div:first-child{border-left:0}

/* actions (not in screenshot; hidden on print) */
.actions{display:flex;gap:8px;justify-content:flex-end;margin:12px 0}
button{padding:10px 14px;border:1px solid #000;background:#fff;cursor:pointer}
button.primary{background:#0d1c70;color:#fff;border-color:#0d1c70}

/* responsive */
@media (max-width:960px){
  .header-grid{grid-template-columns:1fr 80px 1fr}
  .header .crest img{height:56px}
}
@media (max-width:720px){
  .row, .row.split-3, .row.split-2a{grid-template-columns:1fr}
  .cell-h{border-right:0}
  .cell{border-top:var(--b) solid var(--ink)}
  .cell:first-of-type{border-top:0}
  .decl{grid-template-columns:1fr}
  .header-grid{grid-template-columns:1fr}
  .toprule{justify-content:center}
  .gender-right{justify-content:flex-start}
}
</style>
</head>
<body>
<div class="page">

  <!-- HEADER -->
  <header class="header">
    <div class="header-grid">
      <div class="block">
        O'ZBEKISTON RESPUBLIKASI<br>SOG'LIQNI SAQLASH VAZIRLIGI
        <small>BIZNES VA FAN UNIVERSITETI — Toshkent, O'zbekiston — Farabi ko chasi 2, 100109 — Tel.: +(998-90) 3210073</small>
      </div>
      <div class="crest">
        <img src="uzb.png" alt="Uzbekistan Emblem">
      </div>
      <div class="block">
        MINISTRY OF HEALTH OF THE REPUBLIC OF UZBEKISTAN
        <small>UNIVERSITY OF BUSINESS AND SCIENCE — Tashkent, Uzbekistan — Farabi st 2, 100109 — Tel.: +(998-90) 3210073</small>
      </div>
    </div>
    <div class="meta">website: <strong>www.uobs.uz</strong> &nbsp; e-mail: <strong>admissions@uobs.uz</strong></div>
  </header>

  <!-- TITLE -->
  <div class="title">
    <h1>APPLICATION FORM</h1>
    <small>(for foreign applicants)</small>
  </div>

  <form id="uobsForm" method="post" action="save_partial.php" enctype="multipart/form-data" novalidate>

    <!-- FUTURE EDUCATION -->
    <section class="section">
      <div class="bar">FUTURE EDUCATION</div>
      <div class="table">
        <div class="row">
          <div class="cell-h">Proposed Degree program:</div>
          <div class="cell">
            <div class="checkset">
              <label><input type="checkbox" name="bachelor_program" value="1" id="deg-bach"> Bachelor's</label>
              <label><input type="checkbox" name="masters_program" value="1" id="deg-mast"> Master's</label>
              <label><input type="checkbox" name="phd_program" value="1" id="deg-phd"> Ph.D/PG</label>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="cell-h">Specialty / Field of study:</div>
          <div class="cell">
            <select name="intended_study_level" id="intendedSelect" disabled>
              <option value="">— Select specialty —</option>
              <option>Medicine/MBBS/MD</option>
              <option>Dentistry</option>
              <option>Pharmacy</option>
              <option>Pediatrics</option>
              <option>Business Management</option>
              <option>Economics</option>
              <option>Information Technologies</option>
              <option>Tourism and Hospitality Management</option>
              <option>Human Resource Management</option>
              <option>Marketing</option>
              <option>Linguistics</option>
            </select>
          </div>
        </div>
      </div>
    </section>

    <!-- PERSONAL DATA -->
    <section class="section">
      <div class="bar">PERSONAL DATA</div>
      <div class="table">
        <div class="row split-3">
          <div class="cell-h">Surname:</div>
          <div class="cell"><input type="text" name="last_name" placeholder=""></div>
          <div class="cell-h">Name:</div>
          <div class="cell"><input type="text" name="first_name" placeholder=""></div>
        </div>

        <div class="row split-3">
          <div class="cell-h">Middle name:</div>
          <div class="cell"><input type="text" name="middle_name" placeholder=""></div>
          <div class="cell-h">Gender:</div>
          <div class="cell gender-right">
            <label style="margin-right:18px;"><input type="radio" name="gender" value="Male"> Male</label>
            <label><input type="radio" name="gender" value="Female"> Female</label>
          </div>
        </div>

        <div class="row">
          <div class="cell-h">Date of Birth:</div>
          <div class="cell"><input type="date" name="dob"></div>
        </div>

        <div class="row split-2a">
          <div class="cell-h">Place of Birth:</div>
          <div class="cell"><input type="text" name="city_of_birth" placeholder="City"></div>
          <div class="cell"><input type="text" name="country_of_birth" placeholder="Country"></div>
        </div>

        <div class="row">
          <div class="cell-h">Nationality:</div>
          <div class="cell"><input type="text" name="nationality"></div>
        </div>

        <div class="row split-3">
          <div class="cell-h">National passport №:</div>
          <div class="cell"><input type="text" name="passport"></div>
          <div class="cell-h">Date of Expire:</div>
          <div class="cell"><input type="date" name="passport_expiry"></div>
        </div>

        <div class="row">
          <div class="cell-h">Country of visa application:</div>
          <div class="cell"><input type="text" name="destination"></div>
        </div>

        <div class="row">
          <div class="cell-h">Permanent Address:</div>
          <div class="cell"><textarea name="address_line1"></textarea></div>
        </div>

        <div class="row split-3">
          <div class="cell-h">Contact Number:</div>
          <div class="cell" style="gap:8px">
            <input type="text" name="area_code" placeholder="+998" style="max-width:110px">
            <input type="text" name="phone_number" placeholder="Phone number">
          </div>
          <div class="cell-h">Contact E-mail:</div>
          <div class="cell"><input type="email" name="email" placeholder=""></div>
        </div>
      </div>
    </section>

    <!-- EDUCATIONAL BACKGROUND -->
    <section class="section">
      <div class="bar">EDUCATIONAL BACKGROUND</div>

      <div class="subbar">SCHOOL</div>
      <div class="table">
        <div class="row split-3">
          <div class="cell-h">School name:</div>
          <div class="cell"><input type="text" name="previous_institution_name"></div>
          <div class="cell-h">School address:</div>
          <div class="cell"><input type="text" name="previous_institution_street"></div>
        </div>

        <div class="row split-3">
          <div class="cell-h">Attended Since:</div>
          <div class="cell"><input type="date" name="previous_study_start"></div>
          <div class="cell-h">till</div>
          <div class="cell"><input type="date" name="previous_study_graduation"></div>
        </div>

        <div class="row">
          <div class="cell-h">Received Certificate:</div>
          <div class="cell"><input type="text" name="high_school_degree"></div>
        </div>
      </div>

      <div class="subbar">COLLEGE / UNIVERSITY</div>
      <div class="table">
        <div class="row">
          <div class="cell-h">College / University (if Attended) name:</div>
          <div class="cell"><input type="text" name="post_secondary"></div>
        </div>

        <div class="row">
          <div class="cell-h">College / University address:</div>
          <div class="cell"><input type="text" name="previous_institution_city" placeholder="City / Province / Country"></div>
        </div>

        <div class="row split-3">
          <div class="cell-h">Attended Since:</div>
          <div class="cell"><input type="text" name="college_attended_since" placeholder="Month/Year"></div>
          <div class="cell-h">till</div>
          <div class="cell"><input type="text" name="college_attended_till" placeholder="Month/Year"></div>
        </div>

        <div class="row">
          <div class="cell-h">Received Certificate:</div>
          <div class="cell"><input type="text" name="degree_transcripts" placeholder="Bachelor’s Degree / Transcript"></div>
        </div>
      </div>
    </section>

    <!-- APPENDIX -->
    <section class="section">
      <div class="bar">APPENDIX</div>
      <div class="table">
        <div class="row">
          <div class="cell-h">1. Copy of passport</div>
          <div class="cell"><input type="file" name="passport_scan"></div>
        </div>
        <div class="row">
          <div class="cell-h">2. Copies of educational certificates</div>
          <div class="cell"><input type="file" name="degree_transcripts[]" multiple></div>
        </div>
      </div>
      <div style="font-size:12px;padding:8px 2px 0 2px">
        I confirm that the information given in the form is correct.
      </div>
    </section>

    <!-- DECLARATION -->
    <section class="section">
      <div class="decl">
        <div>
          <label style="font-weight:700;display:block;margin-bottom:6px">Date:</label>
          <input type="date" name="application_date">
        </div>
        <div>
          <label style="font-weight:700;display:block;margin-bottom:6px">Applicant’s signature:</label>
          <input type="text" name="applicant_signature" placeholder="Type your name">
        </div>
        <div></div>
      </div>
    </section>

    <div class="actions">
      <button type="reset">Reset</button>
      <button type="submit" class="primary">Submit Application</button>
    </div>

    <input type="hidden" name="submitted" value="1">
  </form>
</div>

<script>
/* Ensure degree checkboxes submit 1/0 like tinyint fields (existing logic) */
document.getElementById('uobsForm').addEventListener('submit', function(){
  this.querySelectorAll('input[type=checkbox]').forEach(cb=>{
    if(!cb.checked){
      const h=document.createElement('input');
      h.type='hidden';h.name=cb.name;h.value='0';
      this.appendChild(h);
    }else cb.value='1';
  });

  // If neither Bachelor's nor Master's is checked, ensure intended_study_level is empty
  const bach = document.getElementById('deg-bach').checked;
  const mast = document.getElementById('deg-mast').checked;
  const intended = document.getElementById('intendedSelect');
  if(!(bach || mast)){
    intended.value = '';
  }
});

/* Enable/disable the intended study select based on degree choices */
(function(){
  const bach = document.getElementById('deg-bach');
  const mast = document.getElementById('deg-mast');
  const phd  = document.getElementById('deg-phd');
  const sel  = document.getElementById('intendedSelect');

  function updateIntendedState(){
    const enable = bach.checked || mast.checked;
    sel.disabled = !enable;
    if(!enable){ sel.value = ''; }
  }

  [bach, mast, phd].forEach(el => el.addEventListener('change', updateIntendedState));
  updateIntendedState(); // init
})();
</script>
</body>
</html>
