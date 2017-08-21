  <div class="form-group row">
  	<div class="col-md-9 col-md-offset-3">
  		<h3>{l s='application' mod='applicant'}</h3>
  	</div>
  </div>
  <div class="block_content">
  	{if isset($msg) && $msg}
  	<p class="{if $nw_error}warning_inline{else}success_inline{/if}">{$msg}</p>
  	{/if}
  	<form class="applicant" method="post">
  		<section class="form-fields">

  			{foreach from=$fields key=field item=attr}
  			{if $attr.type == 'hidden'}
  			<input name="{$field}" type="{$attr.type}" value=""/>
  			{else}
  			<div class="form-group row">
  				<label class="col-md-3 form-control-label" for="{$field}">{$attr.label}</label>
  				<div class="col-md-6">
  					{if $attr.type == 'textarea'}
  					<textarea id="{$field}" class="form-control" name="{$field}" rows="3" {if $attr.required}required{/if}></textarea>
  					{elseif $attr.type == 'checkbox'}
  					<div class="col-md-1">
  						<input id="{$field}" class="form-control" name="{$field}" type="{$attr.type}" value="1" {if $attr.required}required{/if}/>
  					</div>
  					{elseif $attr.type == 'radio'}
  					{foreach from=$attr.choices key=value item=label}
  					<input id="" class="form-control" name="{$field}" type="{$attr.type}" value="{$value}" {if $attr.required}required{/if}/> {l s=$label mod='applicant'}<br>
  					{/foreach}
  					{elseif $attr.type == 'select'}
  					<select class="form-control" name="{$field}" {if $attr.required}required{/if}>
  						{foreach from=$attr.choices key=value item=label}
  						<option value="{$value}" /> {l s=$label mod='applicant'}<br>
  						{/foreach}
  					</select>
  					{else}
  					<input id="{$field}" class="form-control" name="{$field}" type="{$attr.type}" value="" {if $attr.required}required{/if}/>
  					{/if}
  				</div>
  			</div>
  			{/if}
  			{/foreach}

  		</section>

  		<input type="hidden" name="action" value="1" />

  		<footer class="form-footer text-sm-center">
  			<input class="btn btn-primary" type="submit" name="submitApplication" value="{l s='Send' mod='applicant'}">
  		</footer>
  	</form>
  	<div>
  	</div>
  </div>