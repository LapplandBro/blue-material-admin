<div class="card banlist-panel admin-tabs-card">
	<div class="card-body admin-tabs-body">
		<div id="admin-page-menu" class="fw-container">
			<ul class="tab-nav fw-nav admin-tabs-nav">
				{foreach from=$tabs item=tab}
					{$tab.tab}
				{/foreach}
			</ul>
		</div>
	</div>
</div>
