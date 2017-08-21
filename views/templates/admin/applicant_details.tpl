<div class="panel col-lg-12">
	<div class="panel-heading">

		<a href="{$backLink|escape:'html':'UTF-8'}" class="" title="{l s='Back'}" >
			<i class="icon-circle-arrow-left "></i>
		{l s='Back to the list' mod='applicant'}
		</a>
		</div>

		<div class="table-responsive-row clearfix">
			<table class="table store_applicant_view">
				<thead>
					<tr>
						<thcolspan="2">
							<span class="title_box">
							<h1>
								{$applicant.first_name} {$applicant.last_name}
							</h1>
							</span>
						</th>
					</tr>
				</thead>
				<tbody>
					{foreach from=$fields key=field item=attr}
					<tr class="{if $field@iteration is odd}odd{/if}">
						<td class="text-right" width="200">
							{l s=$attr.label mod='applicant'}
						</td>
						<td>
							{$applicant[$field]|nl2br}
						</td>
					</tr>
					{/foreach}
				</tbody>
			</table>
		</div>
	</div>