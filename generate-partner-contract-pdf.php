<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/pdf-generator-base.php';

class EnglishPDFGenerator extends PDFGeneratorBase {
    
    protected function getFilename(): string {
        return 'partner-contract-' . $this->contract['id'] . '-' . date('Y-m-d') . '.pdf';
    }
    
    protected function getContractContent(): string {
        $partnerSignatureHtml = $this->processSignatureImage($this->contract['signature_image']);
        $managerSignatureHtml = $this->getManagerSignature();
        $companyStampHtml = $this->getCompanyStamp();
        $signedDate = $this->formatSignedDate($this->contract['signed_date'] ?? '');
        $partnerEmail = $this->esc($this->contract['representative_email'] ?: $this->contract['company_email']);
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="utf-8">
        <title>Strategic Partnership Agreement</title>
        <style>' . $this->getBaseStyles() . '</style>
        </head>
        <body>
        
        <div class="content-wrapper">
        <div class="header">
            <h1>Strategic Partnership Agreement</h1>
            <div class="subtitle">A Professional Partnership for Global Education Services</div>
        </div>
        
        <div>
            <h4>1. PARTIES</h4>
            <p><strong>Between</strong></p>
            
            <p><strong>Company Name:</strong> ' . $this->esc($this->contract['company_name']) . '</p>
            <p><strong>Representative:</strong> ' . $this->esc($this->contract['representative_name']) . '</p>
            <p><strong>Position:</strong> ' . $this->esc($this->contract['representative_title']) . '</p>
            <p><strong>Email:</strong> ' . $this->esc($this->contract['company_email']) . '</p>
            <p><strong>Phone:</strong> ' . $this->esc($this->contract['company_phone']) . '</p>
            <p><strong>Full Address:</strong> ' . $this->esc($this->contract['company_address']) . '</p>
            
            <p><strong>and</strong></p>
            
            <p><strong>ScholarSync Global Co. Ltd</strong><br>
            Dr Jean Pierre Twajamahoro<br>
            Owner & Managing Director<br>
            Email: infos@scholarsyncglobal.ca<br>
            Phone: +1 (438) 290-6688<br>
            Rwanda Address: Rwanda - Kigali<br>
            Town Center Building (near Simba Supermarket),<br>
            2nd Floor, Door: F2B-022C, Nyarugenge<br>
            Canada Address:<br>
            294 Rue Vezina App 202; Lasalle, Quebec H8R 3M9</p>
        </div>
        
        <h2>2. PURPOSE OF AGREEMENT</h2>
        
        <p>The primary objective of this agreement is to establish a comprehensive and structured student support system, including:</p>
        <ul>
            <li>Document evaluation and eligibility assessment</li>
            <li>University/institution selection</li>
            <li>Admission acquisition</li>
            <li>Partial scholarships and student loan assistance (if applicable)</li>
            <li>Visa counseling and processing</li>
            <li>Travel arrangement</li>
            <li>Airport pickup and settlement assistance in destination country</li>
        </ul>
        
        <h2>3. SCOPE OF PARTNERSHIP</h2>
        
        <h3>3.1 Student Recruitment and Counseling</h3>
        <ul>
            <li>Identification and recruitment of qualified students</li>
            <li>Academic and career counseling tailored to international opportunities</li>
        </ul>
        
        <h3>3.2 Document Evaluation and Admission Process</h3>
        <ul>
            <li>Comprehensive document verification and eligibility assessment</li>
            <li>International university and program selection</li>
            <li>Application preparation and submission</li>
            <li>Admission letter acquisition</li>
            <li>Scholarship and student loan assistance (if applicable)</li>
        </ul>
        
        <h3>3.3 Visa and Immigration Processing</h3>
        <ul>
            <li>Visa counseling under supervision of Dr Jean Pierre Twajamahoro</li>
            <li>Document verification according to destination country laws</li>
            <li>Visa application processing and follow-up</li>
        </ul>
        
        <h3>3.4 Travel and Pre-departure Services</h3>
        <ul>
            <li>Travel planning and flight assistance</li>
            <li>Pre-departure orientation</li>
        </ul>
        
        <h3>3.5 Airport Pickup and Settlement (Key Commitment)</h3>
        <ul>
            <li>Arranging airport pickup in destination country</li>
            <li>Initial accommodation assistance</li>
            <li>Arrival settlement support</li>
            <li>Coordination with local partners</li>
        </ul>
        
        <h2>4. PRIMARY MISSION</h2>
        
        <p>Both parties agree to operate as a global education consultancy firm, offering:</p>
        <ul>
            <li>A "From Evaluation to Settlement" service model</li>
            <li>Covering all international destinations</li>
            <li>Including admission, visa, travel, and arrival support</li>
            <li>This partnership ensures a smooth transition from initial evaluation to complete settlement abroad</li>
        </ul>
        
        <h2>5. ROLES AND RESPONSIBILITIES</h2>
        
        <div class="page-break">
        <h3>5.1 Company Name: ' . $this->esc($this->contract['company_name']) . '</h3>
        <ul>
            <li>Recruit and prepare students</li>
            <li>Assist in initial document collection and verification</li>
            <li>Help with application preparation</li>
            <li>Provide pre-departure guidance</li>
            <li>Maintain communication with applicants</li>
        </ul>
        </div>
        
        <div class="page-break">
        <h3>5.2 ScholarSync Global Co. Ltd</h3>
        <p>(Represented by Dr Jean Pierre Twajamahoro, Owner & Managing Director)</p>
        <ul>
            <li>Conduct initial document evaluation and eligibility assessment</li>
            <li>Assist in university/institution selection</li>
            <li>Support admission acquisition</li>
            <li>Provide partial scholarship and student loan assistance (if applicable)</li>
            <li>Offer professional visa counseling and processing services</li>
            <li>Ensure compliance with destination countries immigration laws</li>
            <li>Manage visa documents and procedures</li>
            <li>Coordinate travel planning</li>
            <li>Arrange airport pickup and settlement assistance in destination country</li>
            <li>Provide post-arrival support if needed</li>
        </ul>
        </div>
        
        <h2>6. FINANCIAL ARRANGEMENTS</h2>
        
        <ul>
            <li>Each party retains the right to charge their own service fees to students according to their internal policies.</li>
            <li>ScholarSync Global Co. Ltd commits to pay application service fees to Company Name: ' . $this->esc($this->contract['company_name']) . ' upon issuance of official admission letter.</li>
            <li>The agreed application fees are:</li>
            <li> Canada: 125 CAD per student</li>
            <li> United States: 100 USD per student</li>
            <li> Europe: 100 EUR per student</li>
            <li> Asia: 100 USD per student</li>
            <li>Payment must be made immediately after admission letter receipt, according to agreed terms.</li>
            <li>Both parties commit to financial transparency and traceability.</li>
        </ul>
        
        <h2>7. ADDED VALUE</h2>
        
        <p>This partnership offers:</p>
        <ul>
            <li>Complete service from evaluation to settlement</li>
            <li>Improved admission and visa success rates</li>
            <li>Financial assistance (scholarships and loans)</li>
            <li>Safe arrival and successful integration abroad</li>
        </ul>
        
        <h2>8. COMMUNICATION AND COORDINATION</h2>
        
        <ul>
            <li>Designation of dedicated representatives</li>
            <li>Continuous student file monitoring</li>
            <li>Real-time communication</li>
        </ul>
        
        <h2>9. CONFIDENTIALITY</h2>
        
        <p>All exchanged information remains strictly confidential.</p>
        
        <h2>10. COMPLIANCE AND ETHICS</h2>
        
        <ul>
            <li>Full compliance with international laws</li>
            <li>Ethical and transparent commitment</li>
            <li>Zero tolerance for fraud</li>
        </ul>
        
        <h2>11. DURATION AND TERMINATION</h2>
        
        <ul>
            <li>Effective upon signature</li>
            <li>Valid for 1 years</li>
            <li>Termination with 30-day written notice</li>
            <li>Mandatory completion of ongoing files</li>
        </ul>
        
        <h2>12. DISPUTE RESOLUTION</h2>
        
        <ul>
            <li>Amicable resolution</li>
            <li>Arbitration if necessary</li>
        </ul>
        
        <h2>13. FORCE MAJEURE</h2>
        
        <p>Neither party shall be liable in case of circumstances beyond their control.</p>
        
        <h2>14. CONCLUSION</h2>
        
        <p>This agreement represents a strategic global partnership aimed at providing comprehensive international education services, from document evaluation to airport pickup and settlement abroad.</p>
        
        <div class="signature-section">
            <h2>15. SIGNATURES</h2>
            <p>This Strategic Partnership Agreement is executed by the authorized representatives of both parties on the date indicated below:</p>
            
            <table style="width:100%;border-collapse:collapse;margin-top:12pt;">
                <tr>
                    <td style="width:50%;vertical-align:top;padding:10px 12px 10px 0;">
                        <p style="font-weight:700;color:#0d47a1;margin:0 0 10px 0;">For ScholarSync Global Co. Ltd</p>
                        <p><strong>Name:</strong> Dr. Jean Pierre Twajamahoro</p>
                        <p><strong>Title:</strong> Owner & Managing Director</p>
                        <p><strong>Signature:</strong></p>
                        ' . $managerSignatureHtml . '
                        <p><strong>Company Stamp:</strong></p>
                        ' . $companyStampHtml . '
                        <p><strong>Date:</strong> ' . $signedDate . '</p>
                    </td>
                    <td style="width:50%;vertical-align:top;padding:10px 0 10px 12px;border-left:1px solid #e5e7eb;">
                        <p style="font-weight:700;color:#0d47a1;margin:0 0 10px 0;">Partner Company</p>
                        <p><strong>Company Name:</strong> ' . $this->esc($this->contract['company_name']) . '</p>
                        <p><strong>Representative Name:</strong> ' . $this->esc($this->contract['representative_name']) . '</p>
                        <p><strong>Title:</strong> ' . $this->esc($this->contract['representative_title']) . '</p>
                        <p><strong>Email:</strong> ' . $partnerEmail . '</p>
                        <p><strong>Phone:</strong> ' . $this->esc($this->contract['company_phone']) . '</p>
                        <p><strong>Company Address:</strong> ' . $this->esc($this->contract['company_address']) . '</p>
                        <p><strong>Signature:</strong></p>
                        <div style="border:1px dashed #9ca3af;padding:6px;min-height:72px;">' . $partnerSignatureHtml . '</div>
                        <p><strong>Date:</strong> ' . $signedDate . '</p>
                    </td>
                </tr>
            </table>
            
            <div class="footer">
                <p>This agreement constitutes the entire understanding between the parties and supersedes all prior discussions, negotiations, and agreements.</p>
                <p>IN WITNESS WHEREOF, the parties have executed this Strategic Partnership Agreement as of the date indicated above.</p>
            </div>
        </div>
        
        </div>
        </body>
        </html>
        ';
    }
}

function generatePartnerContractPDF(int $contractId): ?string {
    global $conn;
    $generator = new EnglishPDFGenerator($conn, $contractId);
    return $generator->generate();
}
