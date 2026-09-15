/*
 * Neutralized stub (was: AlphaImageLoader PNG transparency hack for Win IE 5.5/6).
 *
 * The site does not support IE6, and the original walked document.images and replaced every
 * PNG with img.outerHTML built by string concatenation from img.id/className/title/alt/src,
 * so any attacker-controlled image attribute became markup. No caller remains: the <script>
 * tag in pages/footer.php has been removed.
 *
 * The file is kept because install/dry_run.php and the install manifests reference the path.
 * Original contents are in git history.
 */
