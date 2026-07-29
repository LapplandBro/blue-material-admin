<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>{$title|escape} · Material Admin</title>
	<link rel="Shortcut Icon" href="../images/favicon.ico" />
	<link href="../themes/new_box/css/uploadfile.css?v=20260727b" rel="stylesheet" />
	{literal}
	<style>
	:root{--up-bg:#060b16;--up-card:#0a1225;--up-border:#1a2a45;--up-text:#e8f0ff;--up-muted:#7ea8d4;--up-accent:#2f7fd6;--up-accent-hover:#3d92ef;--up-radius:12px}
	*,*::before,*::after{box-sizing:border-box}
	html,body{margin:0;padding:0;min-height:100%}
	body.upload-page{background:radial-gradient(ellipse at top left,rgba(47,127,214,.18),transparent 55%),linear-gradient(160deg,#080f1f 0%,#060b16 100%);color:var(--up-text);font-family:"Rubik","Segoe UI",system-ui,-apple-system,sans-serif;font-size:14px;line-height:1.45;display:flex;align-items:center;justify-content:center;padding:18px;min-height:100vh}
	.upload-card{width:100%;max-width:440px;background:var(--up-card);border:1px solid var(--up-border);border-radius:var(--up-radius);box-shadow:0 12px 40px rgba(0,0,0,.45);padding:22px 22px 20px}
	.upload-card__head{display:flex;align-items:center;gap:12px;margin-bottom:16px}
	.upload-card__mark{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:9px;background:linear-gradient(145deg,#2f7fd6,#1a4f8c);color:#fff;font-weight:700;font-size:16px;flex:0 0 36px}
	.upload-card__titles{min-width:0}
	.upload-card__title{margin:0;font-size:18px;font-weight:600;letter-spacing:.01em;color:var(--up-text)}
	.upload-card__hint{margin:3px 0 0;font-size:12px;color:var(--up-muted)}
	.upload-card__message{margin:0 0 14px;padding:10px 12px;border-radius:8px;background:rgba(47,127,214,.12);border:1px solid rgba(47,127,214,.35);color:#c5dcff;font-size:13px}
	.upload-card__message b,.upload-card__message strong{color:#fff}
	.upload-form{display:flex;flex-direction:column;gap:12px}
	.upload-drop{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;min-height:120px;padding:18px 14px;border:1px dashed #2a4570;border-radius:10px;background:rgba(8,15,31,.65);cursor:pointer;text-align:center}
	.upload-drop:hover,.upload-drop:focus-within{border-color:var(--up-accent);background:rgba(47,127,214,.08)}
	.upload-drop__icon{font-size:22px;line-height:1;color:var(--up-accent);opacity:.9}
	.upload-drop__text{color:var(--up-text);font-size:13px;font-weight:500}
	.upload-drop__file{color:var(--up-muted);font-size:12px;word-break:break-all;max-width:100%}
	.upload-drop__input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}
	.upload-btn{appearance:none;border:0;border-radius:9px;padding:11px 16px;background:var(--up-accent);color:#fff;font:inherit;font-weight:600;font-size:14px;cursor:pointer}
	.upload-btn:hover{background:var(--up-accent-hover)}
	.upload-denied{text-align:center}
	.upload-denied .upload-card__title{color:#ffcdd2}
	.upload-denied p{margin:8px 0 0;color:var(--up-muted);font-size:13px}
	</style>
	{/literal}
</head>
<body class="upload-page">
	<main class="upload-card" role="main">
		<header class="upload-card__head">
			<span class="upload-card__mark" aria-hidden="true">M</span>
			<div class="upload-card__titles">
				<h1 class="upload-card__title">{$title|escape}</h1>
				<p class="upload-card__hint">Файл: {$formats|escape}</p>
			</div>
		</header>

		{if $message}
		<div class="upload-card__message">{$message}</div>
		{/if}

		<form class="upload-form" action="" method="POST" id="{$form_name|escape}" enctype="multipart/form-data">
			<input name="upload" value="1" type="hidden">
			<label class="upload-drop" for="upload_file_input">
				<span class="upload-drop__icon" aria-hidden="true">&#8679;</span>
				<span class="upload-drop__text">Выберите файл или перетащите сюда</span>
				<span class="upload-drop__file" id="upload_file_name">Файл не выбран</span>
				<input
					id="upload_file_input"
					name="{$input_name|escape}"
					class="upload-drop__input"
					type="file"
					multiple
				>
			</label>
			<button class="upload-btn" type="submit">Загрузить</button>
		</form>
	</main>
	{literal}
	<script>
	(function () {
		var input = document.getElementById('upload_file_input');
		var label = document.getElementById('upload_file_name');
		if (!input || !label) return;
		input.addEventListener('change', function () {
			if (!input.files || !input.files.length) {
				label.textContent = 'Файл не выбран';
				return;
			}
			if (input.files.length === 1) {
				label.textContent = input.files[0].name;
			} else {
				label.textContent = 'Выбрано файлов: ' + input.files.length;
			}
		});
	})();
	</script>
	{/literal}
</body>
</html>
