<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Facture déjà réglée</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; background: #f4f7f6; color: #1a1a1a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
    .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 48px 40px; max-width: 480px; width: 100%; text-align: center; }
    .icon { width: 72px; height: 72px; background: #dbeafe; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; }
    .icon svg { width: 36px; height: 36px; stroke: #2563eb; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
    h1 { font-size: 22px; font-weight: 800; color: #1e40af; margin-bottom: 10px; }
    p  { font-size: 15px; color: #6b7280; line-height: 1.6; }
    .ref { display: inline-block; margin-top: 20px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 10px 20px; font-size: 14px; font-weight: 700; color: #1e40af; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">
      <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
    </div>
    <h1>Facture déjà réglée</h1>
    <p>Cette facture a déjà été réglée. Merci pour votre paiement !</p>
    @if($invoice)
      <div class="ref">{{ $invoice->reference }}</div>
    @endif
  </div>
</body>
</html>
