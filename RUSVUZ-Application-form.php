<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RUSVUZ Application Form</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== CSS Reset & Base Styles ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            border: 1px solid #e1e8ed;
        }

        /* ===== Top Session Banner ===== */
        .session-banner {
            background: linear-gradient(to right, #2c3e50, #4a648c);
            color: white;
            padding: 15px 40px;
            text-align: center;
            font-weight: 600;
            font-size: 18px;
            letter-spacing: 1px;
            position: relative;
        }

        .session-inputs {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            padding: 6px 15px;
            border-radius: 30px;
            margin-left: 15px;
            backdrop-filter: blur(10px);
        }

        .session-inputs input {
            width: 70px;
            padding: 6px 10px;
            border: none;
            border-radius: 6px;
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            background: white;
            color: #2c3e50;
        }

        /* ===== Main Header ===== */
        .main-header {
            background: white;
            padding: 30px 40px 25px;
            border-bottom: 1px solid #eaeaea;
            position: relative;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-circle {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: bold;
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        .logo-text h1 {
            color: #2c3e50;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }

        .logo-text p {
            color: #7f8c8d;
            font-size: 16px;
            font-weight: 500;
        }

        .contact-info {
            text-align: right;
        }

        .contact-info div {
            margin-bottom: 8px;
            color: #555;
            font-size: 14px;
        }

        .contact-info i {
            color: #e74c3c;
            margin-right: 8px;
            width: 18px;
        }

        /* ===== Capital Letters Note ===== */
        .capital-note {
            background: linear-gradient(to right, #fff8e1, #ffecb3);
            border-left: 4px solid #ff9800;
            padding: 15px 20px;
            border-radius: 8px;
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #856404;
            font-weight: 500;
        }

        .capital-note i {
            color: #ff9800;
            font-size: 18px;
        }

        /* ===== Progress Steps ===== */
        .progress-container {
            padding: 30px 40px 0;
            background: #f8f9fa;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: -1px;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 0;
            right: 0;
            height: 4px;
            background: #dfe6e9;
            z-index: 1;
        }

        .progress-bar {
            position: absolute;
            top: 25px;
            left: 0;
            height: 4px;
            background: linear-gradient(to right, #3498db, #2ecc71);
            z-index: 2;
            transition: width 0.5s ease;
            width: 0%;
        }

        .step {
            text-align: center;
            position: relative;
            z-index: 3;
            flex: 1;
        }

        .step-number {
            width: 50px;
            height: 50px;
            background: white;
            border: 4px solid #dfe6e9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-weight: 700;
            color: #7f8c8d;
            font-size: 18px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }

        .step.active .step-number {
            background: #3498db;
            border-color: #2980b9;
            color: white;
            transform: scale(1.1);
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.4);
        }

        .step.completed .step-number {
            background: #2ecc71;
            border-color: #27ae60;
            color: white;
        }

        .step-name {
            font-size: 13px;
            color: #7f8c8d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .step.active .step-name {
            color: #2c3e50;
            font-weight: 700;
        }

        /* ===== Form Container ===== */
        .form-container {
            padding: 40px;
            background: white;
            min-height: 500px;
        }

        .form-section {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .form-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-title {
            color: #2c3e50;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: #3498db;
            background: #e3f2fd;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .section-subtitle {
            color: #7f8c8d;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        /* ===== Form Elements ===== */
        .form-group {
            margin-bottom: 25px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        label.required::after {
            content: " *";
            color: #e74c3c;
            font-weight: bold;
        }

        input, select, textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background: white;
            color: #333;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        input[type="text"], input[type="tel"] {
            text-transform: uppercase;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
            line-height: 1.5;
        }

        /* ===== Radio and Checkbox Groups ===== */
        .radio-group {
            display: flex;
            gap: 25px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 10px 15px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .radio-option:hover {
            background: #f5f5f5;
        }

        .radio-option input[type="radio"] {
            width: 20px;
            height: 20px;
            margin: 0;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }

        /* ===== University & Specialty Section ===== */
        .university-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #3498db;
        }

        .university-card h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .university-card h4 i {
            color: #3498db;
        }

        .specialty-note {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            color: #2e7d32;
            padding: 12px 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .specialty-note i {
            color: #4caf50;
        }

        /* ===== File Upload ===== */
        .file-upload {
            border: 2px dashed #bdc3c7;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s;
            background: #fafafa;
            position: relative;
            overflow: hidden;
        }

        .file-upload:hover {
            border-color: #3498db;
            background: #f0f8ff;
        }

        .upload-btn {
            background: linear-gradient(to right, #3498db, #2980b9);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            font-size: 15px;
        }

        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 15px rgba(52, 152, 219, 0.3);
        }

        .file-name {
            margin-top: 15px;
            color: #27ae60;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .file-input {
            display: none;
        }

        .file-types {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 10px;
        }

        /* ===== Navigation Buttons ===== */
        .form-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px solid #f0f0f0;
        }

        .btn {
            padding: 14px 35px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-prev {
            background: #95a5a6;
            color: white;
        }

        .btn-prev:hover {
            background: #7f8c8d;
            transform: translateX(-3px);
        }

        .btn-next {
            background: linear-gradient(to right, #3498db, #2980b9);
            color: white;
        }

        .btn-next:hover {
            transform: translateX(3px);
            box-shadow: 0 7px 15px rgba(52, 152, 219, 0.4);
        }

        .btn-submit {
            background: linear-gradient(to right, #27ae60, #219653);
            color: white;
        }

        .btn-submit:hover {
            background: linear-gradient(to right, #219653, #1e8449);
            transform: translateY(-2px);
            box-shadow: 0 7px 15px rgba(39, 174, 96, 0.4);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        /* ===== Error & Success Messages ===== */
        .error-message {
            color: #e74c3c;
            font-size: 13px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            display: none;
        }

        .form-group.error .error-message {
            display: flex;
        }

        .form-group.error input,
        .form-group.error select,
        .form-group.error textarea {
            border-color: #e74c3c;
            background: #fff5f5;
        }

        .alert {
            padding: 18px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: none;
            align-items: center;
            gap: 15px;
            font-weight: 500;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 5px solid #c62828;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 5px solid #2e7d32;
        }

        /* ===== Signature Section ===== */
        .signature-section {
            margin-top: 40px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 2px dashed #bdc3c7;
        }

        .signature-line {
            margin-top: 40px;
            padding-top: 10px;
            border-top: 2px solid #7f8c8d;
            width: 300px;
            max-width: 100%;
            text-align: center;
            color: #555;
            font-style: italic;
        }

        /* ===== Footer ===== */
        .footer {
            text-align: center;
            padding: 25px;
            background: #2c3e50;
            color: #ecf0f1;
            font-size: 13px;
            line-height: 1.6;
        }

        .footer p {
            margin-bottom: 8px;
        }

        .footer a {
            color: #3498db;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        /* ===== Responsive Design ===== */
        @media (max-width: 992px) {
            .container {
                border-radius: 12px;
            }
            
            .main-header, .form-container, .progress-container {
                padding: 25px;
            }
            
            .header-top {
                flex-direction: column;
                text-align: center;
            }
            
            .contact-info {
                text-align: center;
            }
            
            .progress-steps {
                flex-wrap: wrap;
                gap: 20px;
            }
            
            .step {
                flex: 0 0 calc(33.333% - 20px);
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .btn {
                padding: 12px 25px;
                font-size: 15px;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 10px;
            }
            
            .session-banner {
                padding: 12px 20px;
                font-size: 16px;
            }
            
            .session-inputs {
                display: block;
                margin: 10px auto 0;
                width: fit-content;
            }
            
            .step {
                flex: 0 0 100%;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .form-navigation {
                flex-direction: column;
                gap: 15px;
            }
        }

        /* ===== Information Boxes ===== */
        .info-box {
            background: #e3f2fd;
            border-left: 5px solid #2196f3;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            color: #0d47a1;
        }

        .warning-box {
            background: #fff3e0;
            border-left: 5px solid #ff9800;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            color: #e65100;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Session Banner -->
        <div class="session-banner">
            <span>20</span>
            <div class="session-inputs">
                <input type="number" id="start_year" min="2024" max="2030" value="2025">
                <span>/</span>
                <input type="number" id="end_year" min="2025" max="2031" value="2026">
                <span>ACADEMIC SESSION</span>
            </div>
        </div>

        <!-- Main Header -->
        <div class="main-header">
            <div class="header-top">
                <div class="logo-section">
                    <div class="logo-circle">R</div>
                    <div class="logo-text">
                        <h1>RUSVUZ APPLICATION FORM</h1>
                        <p>For Foreign Applicants</p>
                    </div>
                </div>
                <div class="contact-info">
                    <div><i class="fas fa-globe"></i> rusvuz.com</div>
                    <div><i class="fas fa-phone-alt"></i> +7 (985) 275-28-78</div>
                    <div><i class="fas fa-envelope"></i> admission@rusvuz.com</div>
                    <div><i class="fas fa-map-marker-alt"></i> Moscow, Russia</div>
                </div>
            </div>
            
            <div class="capital-note">
                <i class="fas fa-exclamation-circle"></i>
                <span>IMPORTANT: Please fill the form in CAPITAL LETTERS for all text fields</span>
            </div>
        </div>

        <!-- Progress Steps -->
        <div class="progress-container">
            <div class="progress-steps">
                <div class="progress-bar" id="progressBar"></div>
                
                <div class="step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-name">Destination</div>
                </div>
                
                <div class="step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-name">Education</div>
                </div>
                
                <div class="step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-name">Personal Data</div>
                </div>
                
                <div class="step" data-step="4">
                    <div class="step-number">4</div>
                    <div class="step-name">Background</div>
                </div>
                
                <div class="step" data-step="5">
                    <div class="step-number">5</div>
                    <div class="step-name">Documents</div>
                </div>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <!-- Alert Messages -->
            <div class="alert alert-error" id="errorAlert">
                <i class="fas fa-exclamation-circle"></i>
                <span id="errorText"></span>
            </div>
            
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle"></i>
                <span id="successText"></span>
            </div>

            <!-- FORM ACTION POINTS TO YOUR PHP ENDPOINT -->
            <form id="applicationForm" method="POST" enctype="multipart/form-data" action="process_rusvuz_application.php">
                <!-- Hidden Fields -->
                <input type="hidden" name="user_id" id="user_id" value="">
                <input type="hidden" name="session_id" id="session_id" value="">
                <input type="hidden" name="academic_session" id="academic_session" value="2025/2026">

                <!-- STEP 1: DESTINATION -->
                <div class="form-section active" id="step1">
                    <h2 class="section-title">
                        <i class="fas fa-map-marker-alt"></i>
                        Step 1: Select Destination Country
                    </h2>
                    
                    <p class="section-subtitle">
                        Choose the country where you want to study. This selection will determine available universities.
                    </p>
                    
                    <div class="form-group">
                        <label class="required">Destination Country</label>
                        <select name="destination" id="destination" required>
                            <option value="">-- Please select your destination country --</option>
                            <option value="Russia">Russia</option>
                            <option value="Poland">Poland</option>
                            <option value="Georgia">Georgia</option>
                        </select>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            Please select a destination country
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> After selecting the country, you will be able to choose universities and specialties in the next step.
                    </div>

                    <div class="form-navigation">
                        <div></div>
                        <button type="button" class="btn btn-next" onclick="nextStep(1)">
                            Continue to Education Details
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 2: EDUCATION -->
                <div class="form-section" id="step2">
                    <h2 class="section-title">
                        <i class="fas fa-graduation-cap"></i>
                        Step 2: Future Education Details
                    </h2>
                    
                    <p class="section-subtitle">
                        Provide details about your intended study program and preferred institutions.
                    </p>
                    
                    <!-- Degree Program -->
                    <div class="form-group">
                        <label class="required">Proposed Degree Program</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="intended_study_level" value="Bachelor's" required>
                                <span>Bachelor's Degree</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="intended_study_level" value="Master's">
                                <span>Master's Degree</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="intended_study_level" value="Ph.D/PG">
                                <span>Ph.D / Postgraduate / Clinical Residency</span>
                            </label>
                        </div>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            Please select degree program
                        </div>
                    </div>

                    <!-- University Selection -->
                    <div class="university-card">
                        <h4><i class="fas fa-university"></i> University Selection</h4>
                        
                        <div class="form-group">
                            <label class="required">Primary University Choice</label>
                            <select name="primary_university" id="primary_university" required disabled>
                                <option value="">-- Select destination country first --</option>
                            </select>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                Please select a university
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>First Alternative University</label>
                                <select name="alternative1" id="alternative1" disabled>
                                    <option value="">-- Optional --</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Second Alternative University</label>
                                <select name="alternative2" id="alternative2" disabled>
                                    <option value="">-- Optional --</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="specialty-note" id="specialtyNote" style="display: none;">
                            <i class="fas fa-lightbulb"></i>
                            <span>Select your primary university to see available specialties</span>
                        </div>
                        
                        <div class="warning-box">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Note:</strong> You can leave alternatives empty and we will propose suitable universities for you.
                        </div>
                    </div>

                    <!-- Specialty/Field of Study -->
                    <div id="specialtySection" style="display: none;">
                        <div class="form-group">
                            <label class="required">Specialty / Field of Study</label>
                            <select name="field_of_study" id="field_of_study" required disabled>
                                <option value="">-- Select university first --</option>
                            </select>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                Please select field of study
                            </div>
                        </div>
                    </div>

                    <!-- Medium of Instruction -->
                    <div class="form-group">
                        <label class="required">Medium of Instruction</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="education_language" value="English" required>
                                <span>English</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="education_language" value="Russian">
                                <span>Russian</span>
                            </label>
                        </div>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            Please select medium of instruction
                        </div>
                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <strong>Important:</strong> If your required field is not available in English, admission will be made in the Russian language.
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-prev" onclick="prevStep(2)">
                            <i class="fas fa-arrow-left"></i>
                            Back to Destination
                        </button>
                        <button type="button" class="btn btn-next" onclick="nextStep(2)">
                            Continue to Personal Data
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 3: PERSONAL DATA -->
                <div class="form-section" id="step3">
                    <h2 class="section-title">
                        <i class="fas fa-user"></i>
                        Step 3: Personal Data
                    </h2>
                    
                    <p class="section-subtitle">
                        Please provide your personal information in CAPITAL LETTERS as shown on your official documents.
                    </p>
                    
                    <!-- Full Name -->
                    <div class="form-group">
                        <label class="required">Full Name</label>
                        <div class="form-row">
                            <div>
                                <input type="text" name="last_name" placeholder="SURNAME" required>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please enter surname
                                </div>
                            </div>
                            <div>
                                <input type="text" name="first_name" placeholder="FIRST NAME" required>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please enter first name
                                </div>
                            </div>
                            <div>
                                <input type="text" name="middle_name" placeholder="MIDDLE NAME (if any)">
                            </div>
                        </div>
                    </div>

                    <!-- Personal Details -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Gender</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="gender" value="male" required>
                                    <span>Male</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="gender" value="female">
                                    <span>Female</span>
                                </label>
                            </div>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                Please select gender
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Marital Status</label>
                            <select name="marital_status">
                                <option value="">-- Select --</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                        </div>
                    </div>

                    <!-- Birth Details -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Date of Birth</label>
                            <input type="date" name="dob" required>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                Please enter date of birth
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Place of Birth</label>
                            <input type="text" name="city_of_birth" placeholder="CITY" required>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                Please enter place of birth
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Country of Birth</label>
                            <input type="text" name="country_of_birth" placeholder="COUNTRY" required>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                Please enter country of birth
                            </div>
                        </div>
                    </div>

                    <!-- Nationality -->
                    <div class="form-group">
                        <label class="required">Nationality</label>
                        <input type="text" name="nationality" placeholder="YOUR NATIONALITY" required>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            Please enter nationality
                        </div>
                    </div>

                    <!-- Passport Information -->
                    <div class="university-card">
                        <h4><i class="fas fa-passport"></i> Passport Information</h4>
                        
                        <div class="form-group">
                            <label class="required">Passport Number</label>
                            <input type="text" name="passport_number" placeholder="PASSPORT NUMBER" required>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                Please enter passport number
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="required">Date of Issue</label>
                                <input type="date" name="passport_issue_date" required>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please enter issue date
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Date of Expiry</label>
                                <input type="date" name="passport_expiry_date" required>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please enter expiry date
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="form-group">
                        <label class="required">Permanent Address</label>
                        <textarea name="address" placeholder="COUNTRY, CITY/TOWN, STREET, HOUSE NUMBER" required></textarea>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            Please enter permanent address
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Contact Number</label>
                            <input type="tel" name="phone_number" placeholder="PHONE NUMBER" required>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                Please enter contact number
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Email Address</label>
                            <input type="email" name="email" placeholder="EMAIL ADDRESS" required>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                Please enter valid email address
                            </div>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-prev" onclick="prevStep(3)">
                            <i class="fas fa-arrow-left"></i>
                            Back to Education
                        </button>
                        <button type="button" class="btn btn-next" onclick="nextStep(3)">
                            Continue to Educational Background
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 4: EDUCATIONAL BACKGROUND -->
                <div class="form-section" id="step4">
                    <h2 class="section-title">
                        <i class="fas fa-book-open"></i>
                        Step 4: Educational Background
                    </h2>
                    
                    <p class="section-subtitle">
                        Provide details about your previous education and language studies.
                    </p>

                    <!-- High School Education -->
                    <div class="university-card">
                        <h4><i class="fas fa-school"></i> High School Education</h4>
                        
                        <div class="form-group">
                            <label class="required">School Name</label>
                            <input type="text" name="previous_institution_name" placeholder="SCHOOL NAME" required>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                Please enter school name
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">School Address</label>
                            <textarea name="previous_institution_street" placeholder="SCHOOL ADDRESS" required></textarea>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                Please enter school address
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="required">Attended From</label>
                                <input type="date" name="previous_study_start" required>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please enter start date
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Attended Till</label>
                                <input type="date" name="previous_study_graduation" required>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Please enter end date
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- College/University (Optional) -->
                    <div class="university-card">
                        <h4><i class="fas fa-university"></i> College/University Education (If Applicable)</h4>
                        
                        <div class="form-group">
                            <label>Institution Name</label>
                            <input type="text" name="college_name" placeholder="COLLEGE/UNIVERSITY NAME">
                        </div>
                        
                        <div class="form-group">
                            <label>Institution Address</label>
                            <textarea name="college_address" placeholder="INSTITUTION ADDRESS"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Attended From</label>
                                <input type="date" name="college_start_date">
                            </div>
                            
                            <div class="form-group">
                                <label>Attended Till</label>
                                <input type="date" name="college_end_date">
                            </div>
                        </div>
                    </div>

                    <!-- Previous Study Questions -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Have you ever studied in Russia?</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="studied_russia" value="No" checked>
                                    <span>No</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="studied_russia" value="Yes">
                                    <span>Yes</span>
                                </label>
                            </div>
                            <div id="russiaDetails" style="display: none; margin-top: 15px;">
                                <label>If Yes, specify year, course and university name:</label>
                                <textarea name="studied_russia_details" placeholder="YEAR, COURSE, UNIVERSITY NAME"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Have you ever studied Russian language?</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="studied_russian" value="No" checked>
                                    <span>No</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="studied_russian" value="Yes">
                                    <span>Yes</span>
                                </label>
                            </div>
                            <div id="russianDetails" style="display: none; margin-top: 15px;">
                                <label>If Yes, when and where:</label>
                                <textarea name="studied_russian_details" placeholder="WHEN AND WHERE"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-prev" onclick="prevStep(4)">
                            <i class="fas fa-arrow-left"></i>
                            Back to Personal Data
                        </button>
                        <button type="button" class="btn btn-next" onclick="nextStep(4)">
                            Continue to Documents
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 5: DOCUMENTS -->
                <div class="form-section" id="step5">
                    <h2 class="section-title">
                        <i class="fas fa-file-upload"></i>
                        Step 5: Documents & Submission
                    </h2>
                    
                    <p class="section-subtitle">
                        Upload all required documents and confirm your application.
                    </p>
                    
                    <div class="warning-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Important:</strong> All documents must be clear, legible scans in PDF, JPG, or PNG format. Maximum file size: 10MB per document.
                    </div>

                    <!-- Required Documents -->
                    <div class="form-group">
                        <label class="required">1. International Passport Copy</label>
                        <div class="file-upload" id="passportUpload">
                            <div class="upload-btn" onclick="document.getElementById('passport_file').click()">
                                <i class="fas fa-passport"></i>
                                Upload Passport Copy
                            </div>
                            <input type="file" name="passport_file" id="passport_file" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="file-name" id="passportFileName">
                                <i class="far fa-file"></i>
                                No file chosen
                            </div>
                            <div class="file-types">Accepted: PDF, JPG, PNG (Max: 10MB)</div>
                        </div>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            Please upload passport copy
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="required">2. Educational Certificates</label>
                        <div class="file-upload" id="certificatesUpload">
                            <div class="upload-btn" onclick="document.getElementById('certificates_file').click()">
                                <i class="fas fa-graduation-cap"></i>
                                Upload Educational Certificates
                            </div>
                            <input type="file" name="certificates_file" id="certificates_file" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="file-name" id="certificatesFileName">
                                <i class="far fa-file"></i>
                                No file chosen
                            </div>
                            <div class="file-types">Include High School Certificate with Transcript</div>
                        </div>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            Please upload educational certificates
                        </div>
                    </div>

                    <!-- Additional Documents (Dynamic) -->
                    <div id="additionalDocuments"></div>

                    <!-- Declaration -->
                    <div class="signature-section">
                        <div class="checkbox-group">
                            <input type="checkbox" name="confirmation" id="confirmation" required>
                            <label for="confirmation" class="required">
                                I confirm that all information provided in this application form is true, complete, and accurate to the best of my knowledge.
                            </label>
                        </div>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            Please confirm the information is correct
                        </div>
                        
                        <div class="form-group" style="margin-top: 25px;">
                            <label class="required">Application Date</label>
                            <input type="date" name="application_date" id="application_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Upload Signed Application Form (Optional)</label>
                            <div class="file-upload" id="signedFormUpload">
                                <div class="upload-btn" onclick="document.getElementById('signed_form').click()">
                                    <i class="fas fa-signature"></i>
                                    Upload Signed Form
                                </div>
                                <input type="file" name="signed_form" id="signed_form" class="file-input" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="file-name" id="signedFormFileName">
                                    <i class="far fa-file"></i>
                                    No file chosen
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-prev" onclick="prevStep(5)">
                            <i class="fas fa-arrow-left"></i>
                            Back to Background
                        </button>
                        <button type="submit" class="btn btn-submit" id="submitBtn">
                            <i class="fas fa-paper-plane"></i>
                            Submit Application
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>RUSVUZ EDUCATIONAL CENTER</strong></p>
            <p>Sivtsev Vrazhek Lane 29/17, Building 1, Moscow, Russia, 119002</p>
            <p>Contact: +7 (985) 275-28-78 | Email: admission@rusvuz.com</p>
            <p>Website: <a href="https://rusvuz.com" target="_blank">rusvuz.com</a></p>
            <p style="margin-top: 15px; font-size: 12px; opacity: 0.8;">
                © 2024 RUSVZ Application System. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        // ===== Database of Universities and Specialties =====
        const universitiesData = {
            'Russia': {
                'Lomonosov Moscow State University': [
                    'Medicine (General Medicine)', 'Pediatrics', 'Dentistry', 
                    'Pharmacy', 'Nursing', 'Computer Science',
                    'Aeronautical Engineering', 'IT Engineering', 'Economics',
                    'Arts & Culture', 'Agriculture', 'International Relations'
                ],
                'Saint Petersburg State University': [
                    'Medicine (General Medicine)', 'Pediatrics', 'Dentistry',
                    'Pharmacy', 'Nursing', 'Computer Science',
                    'Aeronautical Engineering', 'IT Engineering', 'Economics',
                    'Arts & Culture', 'Law', 'International Relations'
                ],
                'Peoples\' Friendship University of Russia (RUDN)': [
                    'Medicine (General Medicine)', 'Pediatrics', 'Dentistry',
                    'Pharmacy', 'Nursing', 'Computer Science',
                    'Engineering', 'Economics', 'Agriculture',
                    'Law', 'International Relations', 'Arts & Culture'
                ],
                'Sechenov University': [
                    'Medicine (General Medicine)', 'Pediatrics', 'Dentistry',
                    'Pharmacy', 'Nursing', 'Public Health',
                    'Veterinary Medicine', 'Medical Biotechnology'
                ],
                'Bauman Moscow State Technical University': [
                    'Aeronautical Engineering', 'Computer Science',
                    'Mechanical Engineering', 'Electrical Engineering',
                    'Civil Engineering', 'Robotics', 'IT Engineering'
                ]
            },
            'Poland': {
                'International European University, Poznan': [
                    'Medicine (General Medicine)', 'Management',
                    'Business Administration', 'Computer Science',
                    'Information Technology', 'Engineering'
                ],
                'Medical University of Silesia, Katowice': [
                    'Medicine (General Medicine)', 'Dentistry',
                    'Pharmacy', 'Medical Biotechnology',
                    'Physiotherapy', 'Nursing', 'Midwifery',
                    'Public Health', 'Dietetics'
                ],
                'Olsztyn University': [
                    'Medicine (General Medicine)', 'Veterinary Medicine',
                    'Biology', 'Biotechnology', 'Environmental Engineering',
                    'Food Technology and Human Nutrition'
                ],
                'Nicolaus Copernicus University in Torun': [
                    'Medicine (General Medicine)', 'Nursing',
                    'Physiotherapy', 'Pharmacy', 'Computer Science'
                ],
                'Warsaw University of Business': [
                    'Business Management', 'Logistics Management',
                    'IT Management', 'Hotel & Tourism Management',
                    'MBA', 'Preparatory English Course'
                ],
                'SWPS University, Warsaw': [
                    'Management and Leadership', 'Computer Science',
                    'Design', 'English Studies', 'Psychology',
                    'Business Psychology', 'Clinical Psychology'
                ],
                'Lazarski University, Warsaw': [
                    'Business Economics', 'Corporate Finance',
                    'International Relations and European Studies',
                    'Law in International Relations', 'E-Commerce Management',
                    'IT Management', 'Marketing', 'Real Estate Management',
                    'Aviation Law and Professional Pilot License'
                ],
                'Warsaw Management University': [
                    'Business Management', 'Computer and Software Engineering',
                    'Environmental Engineering', 'Biology and Biotechnology',
                    'Food Technology and Human Nutrition', 'Veterinary Medicine'
                ]
            },
            'Georgia': {
                'Alte University': [
                    'Medicine (General Medicine)', 'Dentistry',
                    'Computer Science', 'Business Administration',
                    'Economics', 'Arts & Humanities'
                ],
                'Caucasus International University (CIU)': [
                    'Medicine (General Medicine)', 'Dentistry',
                    'Business Administration', 'International Marketing',
                    'Global Policy and Security Studies'
                ],
                'East European University': [
                    'Medicine (General Medicine)', 'Business Administration (BBA)',
                    'MBA', 'Digital Management', 'Dual Degree with Fresenius University'
                ],
                'Georgian National University SEU': [
                    'Medicine (General Medicine)', 'Business Administration',
                    'Law', 'International Relations', 'Computer Science'
                ],
                'Petre Shotadze Tbilisi Medical Academy': [
                    'Medicine (General Medicine)', 'Dentistry',
                    'Nursing', 'Public Health', 'Medical Sciences'
                ],
                'Ken Walker International University (KWIU)': [
                    'Medicine (General Medicine)', 'Dentistry',
                    'Business Administration', 'Computer Science'
                ]
            }
        };

        // ===== Global Variables =====
        let currentStep = 1;
        const totalSteps = 5;
        let selectedCountry = '';
        let selectedUniversity = '';

        // ===== Initialize Application =====
        document.addEventListener('DOMContentLoaded', function() {
            // Generate unique IDs
            document.getElementById('user_id').value = 'user_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            document.getElementById('session_id').value = 'session_' + Date.now();
            
            // Set today's date
            const today = new Date();
            document.getElementById('application_date').value = today.toISOString().split('T')[0];
            
            // Set date constraints
            const dobInput = document.querySelector('input[name="dob"]');
            const maxDob = new Date();
            maxDob.setFullYear(maxDob.getFullYear() - 16);
            dobInput.max = maxDob.toISOString().split('T')[0];
            
            const expiryInput = document.querySelector('input[name="passport_expiry_date"]');
            expiryInput.min = today.toISOString().split('T')[0];
            
            // Initialize progress bar
            updateProgressBar();
            
            // Setup event listeners
            setupEventListeners();
            
            // Update session
            updateSession();
        });

        // ===== Session Management =====
        function updateSession() {
            const startYear = document.getElementById('start_year').value;
            const endYear = document.getElementById('end_year').value;
            document.getElementById('academic_session').value = startYear + '/' + endYear;
        }

        // ===== Event Listeners =====
        function setupEventListeners() {
            // Session year inputs
            document.getElementById('start_year').addEventListener('change', updateSession);
            document.getElementById('end_year').addEventListener('change', updateSession);
            
            // Destination country
            document.getElementById('destination').addEventListener('change', function() {
                selectedCountry = this.value;
                if (selectedCountry) {
                    populateUniversities(selectedCountry);
                }
            });
            
            // University selection
            document.getElementById('primary_university').addEventListener('change', function() {
                selectedUniversity = this.value;
                if (selectedCountry && selectedUniversity) {
                    populateSpecialties(selectedCountry, selectedUniversity);
                    document.getElementById('specialtyNote').style.display = 'none';
                }
            });
            
            // Radio button toggles
            document.querySelectorAll('input[name="studied_russia"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    document.getElementById('russiaDetails').style.display = 
                        this.value === 'Yes' ? 'block' : 'none';
                });
            });
            
            document.querySelectorAll('input[name="studied_russian"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    document.getElementById('russianDetails').style.display = 
                        this.value === 'Yes' ? 'block' : 'none';
                });
            });
            
            // Degree level change
            document.querySelectorAll('input[name="intended_study_level"]').forEach(radio => {
                radio.addEventListener('change', updateAdditionalDocuments);
            });
            
            // File upload handlers
            setupFileUploadHandlers();
            
            // Auto-capitalization
            document.addEventListener('input', function(e) {
                if (e.target.tagName === 'INPUT' && e.target.type === 'text') {
                    e.target.value = e.target.value.toUpperCase();
                }
            });
            
            // Form submission
            document.getElementById('applicationForm').addEventListener('submit', handleSubmit);
        }

        // ===== University Population =====
        function populateUniversities(country) {
            const primarySelect = document.getElementById('primary_university');
            const alt1Select = document.getElementById('alternative1');
            const alt2Select = document.getElementById('alternative2');
            
            // Clear options
            [primarySelect, alt1Select, alt2Select].forEach(select => {
                select.innerHTML = '<option value="">-- Select University --</option>';
                select.disabled = false;
            });
            
            // Show specialty note
            document.getElementById('specialtyNote').style.display = 'block';
            
            // Hide specialty section
            document.getElementById('specialtySection').style.display = 'none';
            
            // Populate universities
            if (universitiesData[country]) {
                Object.keys(universitiesData[country]).forEach(university => {
                    const option1 = new Option(university, university);
                    primarySelect.add(option1);
                    
                    const option2 = new Option(university, university);
                    alt1Select.add(option2);
                    
                    const option3 = new Option(university, university);
                    alt2Select.add(option3);
                });
            }
        }

        // ===== Specialty Population =====
        function populateSpecialties(country, university) {
            const specialtySelect = document.getElementById('field_of_study');
            specialtySelect.innerHTML = '<option value="">-- Select Specialty --</option>';
            specialtySelect.disabled = false;
            
            if (universitiesData[country] && universitiesData[country][university]) {
                universitiesData[country][university].forEach(specialty => {
                    specialtySelect.add(new Option(specialty, specialty));
                });
                
                // Show specialty section
                document.getElementById('specialtySection').style.display = 'block';
            }
        }

        // ===== Additional Documents =====
        function updateAdditionalDocuments() {
            const degreeLevel = document.querySelector('input[name="intended_study_level"]:checked');
            const container = document.getElementById('additionalDocuments');
            
            if (!degreeLevel) {
                container.innerHTML = '';
                return;
            }
            
            let html = '';
            if (degreeLevel.value === "Master's") {
                html = `
                    <div class="form-group">
                        <label class="required">3. Bachelor's Degree Certificate (with transcript)</label>
                        <div class="file-upload" id="bachelorUpload">
                            <div class="upload-btn" onclick="document.getElementById('bachelor_file').click()">
                                <i class="fas fa-file-certificate"></i>
                                Upload Bachelor's Degree
                            </div>
                            <input type="file" name="bachelor_file" id="bachelor_file" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="file-name" id="bachelorFileName">
                                <i class="far fa-file"></i>
                                No file chosen
                            </div>
                            <div class="file-types">Include Bachelor's Certificate and Transcript</div>
                        </div>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            Please upload bachelor's degree certificate
                        </div>
                    </div>
                `;
            } else if (degreeLevel.value === "Ph.D/PG") {
                html = `
                    <div class="form-group">
                        <label class="required">3. Bachelor's Degree Certificate (with transcript)</label>
                        <div class="file-upload" id="bachelorUpload">
                            <div class="upload-btn" onclick="document.getElementById('bachelor_file').click()">
                                <i class="fas fa-file-certificate"></i>
                                Upload Bachelor's Degree
                            </div>
                            <input type="file" name="bachelor_file" id="bachelor_file" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="file-name" id="bachelorFileName">
                                <i class="far fa-file"></i>
                                No file chosen
                            </div>
                            <div class="file-types">Include Bachelor's Certificate and Transcript</div>
                        </div>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            Please upload bachelor's degree certificate
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="required">4. Master's Degree Certificate (with transcript)</label>
                        <div class="file-upload" id="masterUpload">
                            <div class="upload-btn" onclick="document.getElementById('master_file').click()">
                                <i class="fas fa-file-certificate"></i>
                                Upload Master's Degree
                            </div>
                            <input type="file" name="master_file" id="master_file" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="file-name" id="masterFileName">
                                <i class="far fa-file"></i>
                                No file chosen
                            </div>
                            <div class="file-types">Include Master's Certificate and Transcript</div>
                        </div>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            Please upload master's degree certificate
                        </div>
                    </div>
                `;
            }
            
            container.innerHTML = html;
            
            // Setup file upload handlers for new inputs
            setupFileUploadHandlers();
        }

        // ===== File Upload Handlers =====
        function setupFileUploadHandlers() {
            // Handle passport file
            const passportInput = document.getElementById('passport_file');
            if (passportInput) {
                passportInput.addEventListener('change', function(e) {
                    const fileName = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
                    const display = document.getElementById('passportFileName');
                    if (display) {
                        display.innerHTML = `<i class="far fa-file"></i> ${fileName}`;
                        display.style.color = '#27ae60';
                    }
                });
            }
            
            // Handle certificates file
            const certificatesInput = document.getElementById('certificates_file');
            if (certificatesInput) {
                certificatesInput.addEventListener('change', function(e) {
                    const fileName = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
                    const display = document.getElementById('certificatesFileName');
                    if (display) {
                        display.innerHTML = `<i class="far fa-file"></i> ${fileName}`;
                        display.style.color = '#27ae60';
                    }
                });
            }
            
            // Handle bachelor file (if exists)
            const bachelorInput = document.getElementById('bachelor_file');
            if (bachelorInput) {
                bachelorInput.addEventListener('change', function(e) {
                    const fileName = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
                    const display = document.getElementById('bachelorFileName');
                    if (display) {
                        display.innerHTML = `<i class="far fa-file"></i> ${fileName}`;
                        display.style.color = '#27ae60';
                    }
                });
            }
            
            // Handle master file (if exists)
            const masterInput = document.getElementById('master_file');
            if (masterInput) {
                masterInput.addEventListener('change', function(e) {
                    const fileName = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
                    const display = document.getElementById('masterFileName');
                    if (display) {
                        display.innerHTML = `<i class="far fa-file"></i> ${fileName}`;
                        display.style.color = '#27ae60';
                    }
                });
            }
            
            // Handle signed form
            const signedFormInput = document.getElementById('signed_form');
            if (signedFormInput) {
                signedFormInput.addEventListener('change', function(e) {
                    const fileName = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
                    const display = document.getElementById('signedFormFileName');
                    if (display) {
                        display.innerHTML = `<i class="far fa-file"></i> ${fileName}`;
                        display.style.color = '#27ae60';
                    }
                });
            }
        }

        // ===== Progress Bar =====
        function updateProgressBar() {
            const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
            document.getElementById('progressBar').style.width = progress + '%';
            
            // Update step indicators
            document.querySelectorAll('.step').forEach((step, index) => {
                const stepNumber = index + 1;
                if (stepNumber < currentStep) {
                    step.classList.add('completed');
                    step.classList.remove('active');
                } else if (stepNumber === currentStep) {
                    step.classList.add('active');
                    step.classList.remove('completed');
                } else {
                    step.classList.remove('active', 'completed');
                }
            });
        }

        // ===== Step Navigation =====
        function nextStep(current) {
            if (validateStep(current)) {
                hideStep(current);
                currentStep = current + 1;
                showStep(currentStep);
                updateProgressBar();
                
                // Special handling for step 5
                if (currentStep === 5) {
                    updateAdditionalDocuments();
                }
                
                scrollToTop();
            }
        }

        function prevStep(current) {
            hideStep(current);
            currentStep = current - 1;
            showStep(currentStep);
            updateProgressBar();
            scrollToTop();
        }

        function hideStep(step) {
            const stepElement = document.getElementById(`step${step}`);
            stepElement.classList.remove('active');
            setTimeout(() => {
                stepElement.style.display = 'none';
            }, 300);
        }

        function showStep(step) {
            const stepElement = document.getElementById(`step${step}`);
            stepElement.style.display = 'block';
            setTimeout(() => {
                stepElement.classList.add('active');
            }, 10);
        }

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // ===== Validation =====
        function validateStep(step) {
            let isValid = true;
            const stepElement = document.getElementById(`step${step}`);
            const requiredInputs = stepElement.querySelectorAll('[required]');
            
            // Clear previous errors
            stepElement.querySelectorAll('.form-group').forEach(group => {
                group.classList.remove('error');
            });
            
            // Hide alerts
            hideAlerts();
            
            // Validate each required input
            requiredInputs.forEach(input => {
                const formGroup = input.closest('.form-group');
                
                if (!input.value.trim()) {
                    isValid = false;
                    formGroup.classList.add('error');
                } else if (input.type === 'email' && !validateEmail(input.value)) {
                    isValid = false;
                    formGroup.classList.add('error');
                    const errorMsg = formGroup.querySelector('.error-message span');
                    if (errorMsg) {
                        errorMsg.textContent = 'Please enter a valid email address';
                    }
                } else if (input.type === 'date') {
                    // Date validations
                    if (input.name === 'dob') {
                        const dob = new Date(input.value);
                        const minAge = new Date();
                        minAge.setFullYear(minAge.getFullYear() - 16);
                        if (dob > minAge) {
                            isValid = false;
                            formGroup.classList.add('error');
                            const errorMsg = formGroup.querySelector('.error-message span');
                            if (errorMsg) {
                                errorMsg.textContent = 'You must be at least 16 years old';
                            }
                        }
                    }
                    
                    if (input.name === 'passport_expiry_date') {
                        const expiry = new Date(input.value);
                        if (expiry < new Date()) {
                            isValid = false;
                            formGroup.classList.add('error');
                            const errorMsg = formGroup.querySelector('.error-message span');
                            if (errorMsg) {
                                errorMsg.textContent = 'Passport must be valid (not expired)';
                            }
                        }
                    }
                }
            });
            
            // Special validation for step 2
            if (step === 2) {
                const destination = document.getElementById('destination').value;
                const university = document.getElementById('primary_university').value;
                
                if (!destination) {
                    showError('Please select a destination country');
                    return false;
                }
                
                if (!university) {
                    showError('Please select a primary university');
                    return false;
                }
            }
            
            if (!isValid) {
                showError('Please fill all required fields correctly');
            }
            
            return isValid;
        }

        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        // ===== Form Submission =====
        async function handleSubmit(e) {
            e.preventDefault();
            
            if (!validateStep(5)) {
                return;
            }
            
            // Show loading
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            
            try {
                // Collect selected universities for selected_universities JSON field
                const universities = [
                    document.getElementById('primary_university').value,
                    document.getElementById('alternative1').value,
                    document.getElementById('alternative2').value
                ].filter(uni => uni);
                
                // Create hidden input for universities
                let universitiesInput = document.querySelector('input[name="selected_universities"]');
                if (!universitiesInput) {
                    universitiesInput = document.createElement('input');
                    universitiesInput.type = 'hidden';
                    universitiesInput.name = 'selected_universities';
                    e.target.appendChild(universitiesInput);
                }
                universitiesInput.value = JSON.stringify(universities);
                
                // Send form data to PHP endpoint
                const formData = new FormData(e.target);
                
                const response = await fetch('process_rusvuz_application.php', {
                    method: 'POST',
                    body: formData
                });
                
                const rawText = await response.text();
console.log("RAW SERVER RESPONSE:", rawText);

let result;
try {
    result = JSON.parse(rawText);
} catch (e) {
    showError("Server error: invalid response. Check logs.");
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalText;
    return;
}

                
                if (result.status === 'success') {
                    showSuccess(result.message || 'Application submitted successfully!');
                    
                    // Redirect after 3 seconds
                    setTimeout(() => {
                        window.location.href = 'thank-you.html';
                    }, 3000);
                } else {
                    showError(result.message || 'Submission failed. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
                
            } catch (error) {
                console.error('Error:', error);
                showError('Network error. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }

        // ===== UI Helpers =====
        function showError(message) {
            const alert = document.getElementById('errorAlert');
            document.getElementById('errorText').textContent = message;
            alert.style.display = 'flex';
            scrollToTop();
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }

        function showSuccess(message) {
            const alert = document.getElementById('successAlert');
            document.getElementById('successText').textContent = message;
            alert.style.display = 'flex';
            scrollToTop();
        }

        function hideAlerts() {
            document.getElementById('errorAlert').style.display = 'none';
            document.getElementById('successAlert').style.display = 'none';
        }
    </script>
</body>
</html>