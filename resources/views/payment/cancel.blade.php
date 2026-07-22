<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Paiement annulé</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; background: #f4f7f6; color: #1a1a1a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
    .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 48px 40px; max-width: 480px; width: 100%; text-align: center; }
    .icon { width: 72px; height: 72px; background: #fef9c3; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; }
    .icon svg { width: 36px; height: 36px; stroke: #ca8a04; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
    h1 { font-size: 22px; font-weight: 800; color: #92400e; margin-bottom: 10px; }
    p  { font-size: 15px; color: #6b7280; line-height: 1.6; }
    .retry-btn { display: inline-block; margin-top: 28px; background: #E91E63; color: #fff; font-weight: 700; font-size: 14px; padding: 12px 28px; border-radius: 6px; text-decoration: none; }
    .retry-btn:hover { background: #c2185b; }
    .cancelled-note { margin-top: 16px; font-size: 13px; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    </div>

    @if($cancelled ?? false)
      <h1>Facture annulée</h1>
      <p>Cette facture a été annulée et ne peut plus être réglée en ligne.</p>
      <p class="cancelled-note">Veuillez contacter l'émetteur si vous avez des questions.</p>
    @else
      <h1>Paiement non complété</h1>
      <p>Votre paiement n'a pas été effectué. Vous pouvez réessayer à tout moment.</p>
      @if($invoice)
        <a href="{{ request()->getSchemeAndHttpHost() }}/pay/{{ $invoice->uuid }}" class="retry-btn">
          Réessayer →
        </a>
      @endif
    @endif
  </div>
</body>
</html>
