Option Explicit
Dim args, docxPath, pdfPath, word, doc, pdfDir, fso
Set args = WScript.Arguments
If args.Count < 2 Then
    WScript.StdErr.WriteLine "Usage: convert-docx-to-pdf.vbs <docx> <pdf>"
    WScript.Quit 1
End If
docxPath = args(0)
pdfPath = args(1)
Set fso = CreateObject("Scripting.FileSystemObject")
pdfDir = fso.GetParentFolderName(pdfPath)
If Not fso.FolderExists(pdfDir) Then
    fso.CreateFolder pdfDir
End If
If fso.FileExists(pdfPath) Then
    fso.DeleteFile pdfPath, True
End If
Set word = CreateObject("Word.Application")
word.Visible = False
word.DisplayAlerts = 0
Set doc = word.Documents.Open(docxPath, False, True)
doc.SaveAs2 pdfPath, 17
doc.Close False
word.Quit
Set doc = Nothing
Set word = Nothing
If Not fso.FileExists(pdfPath) Then
    WScript.StdErr.WriteLine "Word did not create PDF"
    WScript.Quit 2
End If
WScript.Echo pdfPath
