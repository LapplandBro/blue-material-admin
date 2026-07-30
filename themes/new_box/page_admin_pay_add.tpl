<form action="" method="post">
	<input type="hidden" name="pay_card_admin" value="pay_card_add" />	
	{if $sb_csrf}<input type="hidden" name="sb_csrf" value="{$sb_csrf|escape}" />{/if}
	<input type="hidden" id="card_gr_web" name="card_gr_web" />
	<input type="hidden" id="card_gr_srv" name="card_gr_srv" />
	<input type="hidden" id="srv_check_int" name="srv_check_int" />
	<div class="card">
		<div class="card-header">
		<h2>Новый ваучер
			<small>Ключ для гостя · создаёт админа при активации на сайте</small>
		</h2>
		</div>
		
		<div class="alert alert-info m-l-15 m-r-15" role="alert">
			<p class="m-b-5"><b>Краткая памятка</b></p>
			<ul class="m-b-5 p-l-20">
				<li><b>Ключ</b> — 32 hex-символа (16 случайных байт). Жмите «Сгенерировать», не выдумывайте вручную.</li>
				<li><b>Срок админки</b> — целое число <b>дней</b> (например <code>30</code>). <code>0</code> = навсегда.</li>
				<li><b>Группа (сервер)</b> — права в игре (SourceMod). «Без группы» — только веб или как настроите дальше.</li>
				<li><b>Группа (веб)</b> — права в этой панели. Не выдавайте «Главный админ» случайным людям.</li>
				<li><b>Сервер</b> — на каких серверах будут права. «Без сервера» / ничего не отмечено — гость сам выберет при активации.</li>
			</ul>
			<p class="m-b-0">Активирует ключ <b>только гость</b> (не залогиненный): меню «Активировать ваучер» или <code>index.php?p=pay</code>. Подсказки у полей — на <img class="sb-ico sb-ico-help" src="images/icons/help.svg" width="16" height="16" alt="?" />.</p>
		</div>
		<div class="form-horizontal" role="form">
		<div class="card-body card-padding p-b-0">
			<div class="form-group m-b-5">
				<label for="card_key" class="col-sm-3 control-label text-right">{help_icon title="Ключ" message="32 hex-символа (0-9, a-f), 128 bit энтропии. Нажмите «Сгенерировать» — crypto.getRandomValues / random_bytes."} Ключ</label>
				<div class="col-xs-6">
					<div class="fg-line">
						<input type="text" TABINDEX=1 class="form-control input-mask" id="card_key" name="card_key" data-mask="AAAA-AAAA-AAAA-AAAA-AAAA-AAAA-AAAA-AAAA" placeholder="a1b2-c3d4-e5f6-7890-abcd-ef01-2345-6789" value="{$card_key_default|escape}" maxlength="39" spellcheck="false" autocomplete="off" />
					</div>
				</div>
                    <button type="button" class="btn btn-primary waves-effect m-t-5 btn-icon-text" onclick="var el=document.getElementById('card_key'); if(el) el.value=sbVoucherGenerateHex(16);"><i class="zmdi zmdi-refresh-alt"></i> Сгенерировать</button>
			</div>
			<div class="form-group m-b-5">
				<label for="card_exp" class="col-sm-3 control-label text-right">{help_icon title="Срок (дни)" message="Число дней действия админки с момента активации. Примеры: 7, 30, 90. Ноль (0) — навсегда, без срока."} Срок админки (дни)</label>
				<div class="col-xs-4">
					<div class="fg-line">
						<input type="text" TABINDEX=1 class="form-control" id="card_exp" name="card_exp" placeholder="Например: 30  или  0 = навсегда" />
					</div>
				</div>
			</div>
			<div class="form-group m-b-5">
				<label class="col-sm-3 control-label text-right">{help_icon title="Серверная группа" message="Группа прав в игре (флаги SourceMod). Будет выдана тому, кто активирует ключ."} Группа (сервер)</label>
				<div class="col-sm-9 p-t-10">
						<table>
								{foreach from="$server_admin_group_list" item="server_wg"}
								<tr>
										<td>
											<label for="dd_{$server_wg.id}_dd" class="radio radio-inline m-r-20 p-t-0">
												<input type="radio" value="{$server_wg.name}" id="dd_{$server_wg.id}_dd" name="inlineRadioOptions_srv" hidden="hidden" onchange="var el=$id('card_gr_srv'); if(el) el.value=this.value;" />
												<i class="input-helper"></i> {$server_wg.name}
											</label>
										</td>
								</tr>
								{/foreach}
								<tr>
										<td>
											<label for="no_group_web" class="radio radio-inline m-r-20 p-t-0">
												<input type="radio" value="" id="no_group_web" name="inlineRadioOptions_srv" hidden="hidden" onchange="var el=$id('card_gr_srv'); if(el) el.value=this.value;" />
												<i class="input-helper"></i> <span class="c-red">Без группы</span>
											</label>
										</td>
								</tr>
						</table>
				</div>
			</div>
			<div class="form-group m-b-5">
				<label class="col-sm-3 control-label text-right">{help_icon title="Веб-группа" message="Права в этой панели (баны, настройки и т.д.). Будет выдана при активации ключа гостем."} Группа (веб)</label>
				<div class="col-sm-9 p-t-10">
						<table>
								{foreach from="$server_group_list" item="server_g"}
								<tr>
										<td>
											<label for="dp_{$server_g.gid}_pd" class="radio radio-inline m-r-20 p-t-0">
												<input type="radio" value="{$server_g.name}" id="dp_{$server_g.gid}_pd" name="inlineRadioOptions_web" hidden="hidden" onchange="var el=$id('card_gr_web'); if(el) el.value=this.value;" />
												<i class="input-helper"></i> {$server_g.name}
											</label>
										</td>
								</tr>
								{/foreach}
								<tr>
									<td>
										<label for="no_group_srv" class="radio radio-inline m-r-20 p-t-0">
											<input type="radio" value="0" id="no_group_srv" name="inlineRadioOptions_web" hidden="hidden" onchange="var el=$id('card_gr_web'); if(el) el.value=this.value;" />
											<i class="input-helper"></i> <span class="c-red">Без группы</span>
										</label>
									</td>
								</tr>
						</table>
				</div>
			</div>
			<div class="form-group m-b-5">
				<label class="col-sm-3 control-label text-right">{help_icon title="Серверы" message="Отметьте серверы, где будут права. Если ничего не выбрать (или только «Без сервера») — гость сам выберет серверы при активации."} Серверы</label>
				<div class="col-sm-9 p-t-5">
						<table>
								{foreach from="$server_list" item="server"}
									<tr>
										<td>
											<div class="checkbox">
												<label for="s_{$server.sid}_s">
													<input type="checkbox" name="servers[]" id="s_{$server.sid}_s" value="s{$server.sid}" hidden="hidden" onchange="Check_cal();" />
													<i class="input-helper"></i> <span id="sa{$server.sid}"><i>Получение имени сервера... {$server.ip}:{$server.port}</i></span>
												</label>
											</div>
										</td>
									</tr>
								{/foreach}
								<tr>
									<td>
										<div class="checkbox">
											<label for="s_no_srv">
												<input type="checkbox" name="servers[]" id="s_no_srv" name="s_no_srv" value="-1" hidden="hidden" onchange="Check_cal();" />
												<i class="input-helper"></i> <span class="c-red">Без сервера</span>
											</label>
										</div>
									</td>
								</tr>
						</table>
				</div>
			</div>
		</div>
		</div>
		<div class="card-body card-padding text-center">
			{sb_button text="Добавить" icon="<i class='zmdi zmdi-check-all'></i>" class="bgm-blue btn-icon-text" id="pay_key_send" submit=true}
			
			{sb_button text="Назад" onclick="window.location.href='index.php?p=admin&c=pay'" icon="<i class='zmdi zmdi-undo'></i>" class="bgm-lightblue btn-icon-text" id="aback"}
		</div>
	</div>
</form>
{$server_script}