<?php
session_start();
require_once 'conexao.php';

// Verifica se o usuário é admin
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$stmt = $conn->prepare("SELECT nivel_acesso FROM usuarios WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $usuario_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['nivel_acesso'] !== 'admin') {
    header("Location: calculo.php"); // Bloqueia e redireciona
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>DACTe - CTe</title>
<style>
body { font-family: sans-serif; padding: 0; background: #f0f0f0; }
.no-print { margin: 10px; display:flex; align-items:center; gap:5px; }
input, button { margin-right: 5px; margin-bottom: 5px; }
.print-page {
    width: 210mm;
    min-height: 297mm;
    padding: 10mm;
    background: #fff;
    color: #000;
    box-sizing: border-box;
    margin: 0 auto;
}
.border { border: 1px solid #ccc; padding: 6px; margin-bottom: 8px; border-radius: 6px; background: #fafafa; box-shadow: 1px 1px 4px rgba(0,0,0,0.05); page-break-inside: avoid; }
.small-text { font-size: 10px; }
.bold-text { font-weight: bold; }
.chave-text { font-size: 9px; }
.header-bg { background: #e0e0e0; padding: 5px; border-radius: 4px; font-weight: bold; page-break-inside: avoid; }
.header-text { font-size: 16px; }
.header-emit { font-size: 18px; font-weight: bold; }
.section-header { background: #e8e8e8; padding: 3px 6px; border-radius: 4px; font-weight: bold; margin-bottom: 4px; }
.highlight { font-weight: bold; color: #333; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #ccc; padding: 3px 6px; text-align: left; }
th { background: #f5f5f5; }
.flex { display: flex; justify-content: space-between; align-items: flex-start; }
.text-right { text-align: right; }
.qr-container { display: flex; flex-direction: row; gap: 10px; align-items: center; justify-content: flex-end; }
#pdfFilename { margin-left:auto; font-weight:bold; font-size:12px; }
@media print { .no-print { display: none; } .print-page { page-break-after: avoid; } }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.11.0/html2pdf.bundle.min.js"></script>
</head>
<body>

<div class="no-print">
  <input type="file" id="fileInput" accept=".xml">
  <button onclick="pasteXml()">Colar XML</button>
  <button onclick="window.print()">Imprimir</button>
  <button onclick="exportPDF()">Exportar PDF</button>
  <span id="pdfFilename"></span>
</div>

<div id="danfe" class="print-page">
  <h2 style="text-align:center;margin-bottom:10px;">DACTe - CTe</h2>
</div>

<script>
let parsedXml = null;

function stripNS(x){
  return x.replace(/xmlns(:\w+)?="[^"]*"/g,"")
          .replace(/<\/?([a-zA-Z0-9_-]+):/g,"<$1-")
          .replace(/<\/?([a-zA-Z0-9_-]+)-/g,(m,p)=>m.replace(p+"-",""));
}

function parseXML(x){
  try {
    const d = new DOMParser().parseFromString(stripNS(x),"application/xml");
    if(d.querySelector("parsererror")) throw new Error("Erro ao parsear XML");
    return { doc: d, raw: x };
  } catch(e) { alert("Erro: "+e.message); return null; }
}

function t(tag,p){ const e=p?.getElementsByTagName(tag)[0]; return e?e.textContent.trim():"—"; }

function formatNumber(num){ 
    if(!num||isNaN(num)) return num; 
    return Number(num).toLocaleString("pt-BR",{minimumFractionDigits:2,maximumFractionDigits:2}); 
}

function render(){
  if(!parsedXml) return;
  const d = parsedXml.doc,
        i = d.getElementsByTagName("ide")[0],
        r = d.getElementsByTagName("rem")[0],
        dest = d.getElementsByTagName("dest")[0],
        c = d.getElementsByTagName("infCarga")[0],
        v = d.getElementsByTagName("vPrest")[0],
        qr = t("qrCodCTe", d),
        chave = d.getElementsByTagName("infCte")[0]?.getAttribute("Id")?.replace("CTe",""),
        toma = t("toma", d);

  const emitCNPJ = t("CNPJ", d.getElementsByTagName("emit")[0]);
  const emitCPF  = t("CPF", d.getElementsByTagName("emit")[0]);
  const emitDoc = emitCNPJ && emitCNPJ !== "—" ? emitCNPJ : emitCPF;

  let tomadorNome;
  if(toma==="0"){ tomadorNome = t("xNome", r); } 
  else if(toma==="1" || toma==="3"){ tomadorNome = t("xNome", dest); } 
  else { tomadorNome = "Tomador"; }

  // Atualizar nome do PDF no menu superior
  const primeiroNome = tomadorNome.split(" ")[0];
  const nCT = t("nCT", i);
  const filename = `DACTe_${primeiroNome}_${nCT}.pdf`;
  document.getElementById("pdfFilename").textContent = filename;

  let h = `
  <div class="flex border header-bg header-text">
    <div>
      <div class="header-emit">Emitente: ${t("xNome", d.getElementsByTagName("emit")[0])}</div>
      ${emitDoc}<br>
      ${t("xMun", d.getElementsByTagName("emit")[0])}/${t("UF", d.getElementsByTagName("emit")[0])}
    </div>
    <div class="text-right">
      <span class="highlight">DACTe - CTe</span><br>
      Modelo: ${t("mod", i)} Série: ${t("serie", i)} Nº: ${t("nCT", i)}<br>
      Emissão: ${t("dhEmi", i)}
    </div>
  </div>

  <div class="border header-bg small-text">
    <b>Identificação</b><br>
    <span class="chave-text">${chave}</span>
  </div>

  <div class="border small-text" style="background:#fdfdfd;">
    <div class="section-header">Remetente</div>
    ${t("xNome", r)}<br>${t("CNPJ", r)}<br>${t("xMun", r)}/${t("UF", r)}
  </div>

  <div class="border small-text" style="background:#fdfdfd;">
    <div class="section-header">Destinatário</div>
    ${t("xNome", dest)}<br>${t("CNPJ", dest)}<br>${t("xMun", dest)}/${t("UF", dest)}
  </div>

  <div class="border small-text" style="background:#fdfdfd;">
    <div class="section-header">Tomador do Serviço</div>
    ${tomadorNome}
  </div>

  <div class="border small-text" style="background:#fdfdfd;">
    <div class="section-header">Carga</div>
    <span style="font-size:8px">
      Produto: ${t("proPred", c)}<br>
      Quantidade: ${formatNumber(t("qCarga", c))} ${t("tpMed", c)}<br>
      Valor: R$ ${formatNumber(t("vCarga", c))}
    </span>
  </div>`;

  if(v){
    h += `
    <div class="border" style="background:#fdfdfd; display:flex; justify-content:space-between; align-items:center;">
      <div class="section-header" style="font-size:20px;">Valores</div>
      <div style="font-weight:bold; font-size:22px;">FRETE: R$ ${formatNumber(t("vRec", v))}</div>
    </div>`;
  }

  h += `
  <div class="border header-bg small-text" style="display:flex; justify-content:space-between; align-items:center; page-break-inside:avoid;">
    <div style="font-size:8px; line-height:1.2;">
      Gerado em ${new Date().toLocaleString()}<br><br>
      RNTRC: ${t("RNTRC", d)}
    </div>
    <div class="qr-container">
      <svg id="barcode" style="width:120px; height:40px;"></svg>
      <div id="qrcode" style="width:80px; height:80px;"></div>
    </div>
  </div>`;

  document.getElementById("danfe").innerHTML = h;

  // QRCode
  document.getElementById("qrcode").innerHTML="";
  if(qr) new QRCode(document.getElementById("qrcode"), {text:qr,width:80,height:80});

  // Código de Barras
  if(chave){
    JsBarcode("#barcode", chave, {format:"CODE128", width:1.2, height:40, displayValue:false});
  }
}

document.getElementById("fileInput").addEventListener("change", function(e){
  const f = e.target.files[0]; if(!f) return;
  const r = new FileReader();
  r.onload = function(ev){ parsedXml = parseXML(ev.target.result); render(); };
  r.readAsText(f,"utf-8");
});

function pasteXml(){
  const x = prompt("Cole o XML do CTe:");
  if(!x) return;
  parsedXml = parseXML(x); render();
}

/// Exportar PDF em nova aba
function exportPDF(){
  const element = document.getElementById("danfe");
  html2pdf().set({
    margin: 5,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2, scrollY: 0, width: 800 },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
  }).from(element).toPdf().get('pdf').then(function (pdf) {
    const blob = pdf.output('blob');
    const url = URL.createObjectURL(blob);
    window.open(url, '_blank'); // Abre em nova aba
  });
}
</script>

</body>
</html>