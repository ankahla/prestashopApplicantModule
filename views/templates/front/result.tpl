  <div class="form-group row">
  	<div class="col-md-9 col-md-offset-3">
  		<h3>{l s='application' mod='applicant'}</h3>
  	</div>
  </div>
  <div class="block_content">
  	{if isset($applicant_msg) && $applicant_msg}
  	<p class="alert {if $applicant_error}alert-danger{else}alert-success{/if}">{$applicant_msg}</p>
  	{/if}

  </div>