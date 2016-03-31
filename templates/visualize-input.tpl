{extends file="form.tpl"}

{block name="form-content"}

	<div class="form-group">
		<label class="col-sm-{$formLabelWidth} control-label" for="url">Calendar Feed</label>
		<div class="col-sm-{12 - $formLabelWidth}">
			<div class="input-group">
				<input type="text" id="url" name="url" class="form-control" placeholder="https://calendar/url or webcal://calendar/url" />
				<span class="input-group-btn">
					<button type="submit" class="btn btn-primary has-spinner">Visualize <span class="spinner"><i class="fa fa-refresh fa-spin"></i></span></button>
				</span>
			</div>
		</div>
	</div>

{/block}

{block name="form-buttons"}{/block}