{extends file="form.tpl"}

{block name="form-content"}

	<div class="form-group">
		<label class="col-sm-{$formLabelWidth} control-label" for="cal">Calendar Feed</label>
		<div class="col-sm-{12 - $formLabelWidth}">
			<div class="input-group">
				<input type="text" id="cal" name="cal" class="form-control" value="{$cal|default:""}" placeholder="https://calendar/url or webcal://calendar/url" />
				<span class="input-group-btn">
					<button type="submit" class="btn btn-primary has-spinner">Sync <span class="spinner"><i class="fa fa-refresh fa-spin"></i></span></button>
				</span>
			</div>
			<p class="help-block">If you are syncing a Google Calendar, please be sure to use the <code>Private iCal URL</code> for the calendar to allow access to all calendar event information.</p>
		</div>
	</div>
	
	<div class="col-sm-offset-{$formLabelWidth}">
		<div class="panel panel-default">
			<div class="panel-heading" data-toggle="collapse" data-target="#schedule-collapse">
				<h5>Sync Schedule Configuration</h5>
			</div>
			<div class="panel-body collapse" id="schedule-collapse">
				
				<div class="readable-width">
					<p>Schedule automatic updates from this feed to this course.</p>
					
					<p><em>Warning:</em> The sync will not overwrite existing events in Canvas (so: no merge). <em>Only</em> changes made in the original ICS calendar feed will be synced. Changes made in Canvas will be ignored (and, if the event is changed in the calendar feed subsequently, overwritten). <em>Only</em> additions made in the calendar feed will be synced. Additions made in Canvas will not be part of the sync (and will never be affected by the sync). <em>Only</em> deletions made in the calendar feed will be synced. Deletions made in Canvas will be ignored (and, if the event is subsequently changed in the calendar feed, it will be resynced to Canvas).</p>
				</div>
	
				<div class="form-group">
					<label for="schedule" class="control-label col-sm-{$formLabelWidth}">Schedule</label>
					<div class="col-sm-4">
						<select id="schedule" name="schedule" class="form-control">
							<option value="once">One-time import only</option>
							<optgroup label="Recurring">
								<option value="weekly">Weekly (Saturday at midnight)</option>
								<option value="daily">Daily (at midnight)</option>
								<option value="hourly" selected="selected">Hourly (at the top of the hour)</option>
								<option value="custom">Define your own schedule (below)</option>
							</optgroup>
						</select>
					</div>
				</div>
				
				<div class="form-group">
					<label for="crontab" class="control-label col-sm-{$formLabelWidth}">Custom schedule</label>
					<div class="col-sm-{12 - $formLabelWidth}">
						<input id="crontab" name="crontab" type="text" class="form-control" value="{$crontab|default: ''}" placeholder="0 0 * * *" />
						<p class="help-block"><em>Warning:</em> Not for the faint of heart! Enter a valid crontab time specification (e.g. <code>0 0 * * *</code>). For more information, <a target=\"_blank\" href=\"http://www.linuxweblog.com/crotab-tutorial\">refer to this tutorial.</a></p>
					</div>
				</div>
				
			</div>
		</div>
	
		<div class="panel panel-default">
			<div class="panel-heading" data-toggle="collapse" data-target="#regexp-collapse">
				<h5>Regular Expression Filtering</h5>
			</div>
			<div class="panel-body collapse" id="regexp-collapse">
	
				<div class="readable-width">
					<p><em>Note:</em> The regular expression match is applied to the <em>title</em> of an event <em>only</em>, and the event must both match the include regular expression <em>and</em> not match the exclude regular expression to be included. Note also that the regular expressions are <em>case-sensitive</em>.</p>
				</div>
				
				<div class="form-group">
					<div class="col-sm-offset-{$formLabelWidth}">
						<div class="checkbox">
							<label for="enable_regexp_filter" class="control-label">
								<input id="enable_regexp_filter" name="enable_regexp_filter" type="checkbox" value="enable_filter" />
								Enable regular expression filtering
							</label>
						</div>
					</div>
				</div>
				
				<div class="form-group">
					<label for="include_regexp" class="control-label col-sm-{$formLabelWidth}">Include RegEx</label>
					<div class="col-sm-{12 - $formLabelWidth}">
						<input id="include_regexp" name="include_regexp" type="text" value="{$includeRegexp|default: ''}" placeholder=".*" class="form-control" />
						<p class="help-block">A regular expression to include in the import (e.g. <code>.*</code>).</p>
					</div>
				</div>
	
				<div class="form-group">
					<label for="exclude_regexp" class="control-label col-sm-{$formLabelWidth}">Exclude RegEx</label>
					<div class="col-sm-{12 - $formLabelWidth}">
						<input id="exclude_regexp" name="exclude_regexp" type="text" value="{$excludeRegexp|default: ''}" placeholder="\[PAR\]" class="form-control" />
						<p class="help-block">A regular expression to exclude from the import (e.g. <code>\[PAR\]</code>).</p>
					</div>
				</div>
	
			</div>
		</div>
	</div>

	{assign var="formButton" value="Sync Hourly"}

{/block}

{block name="form-buttons"}
{/block}