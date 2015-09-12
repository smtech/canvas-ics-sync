{extends file="form.tpl"}

{block name="form-content"}

	<div class="form-group">
		<label class="col-sm-{$formLabelWidth} control-label" for="cal">Calendar Feed</label>
		<div class="col-sm-{12 - $formLabelWidth}">
			<input type="text" id="cal" name="cal" class="form-control" value="{$cal|default:""}" placeholder="Use the Private iCal URL for your Google Calendar" />
		</div>
	</div>

	{assign var="formButton" value="Sync Hourly"}

{/block}