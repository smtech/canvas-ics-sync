{extends file="page.tpl"}

{block name="content"}

<div class="container page-header">
	<h1>ICS Visualizer</h1>
</div>

<div class="container">
	{foreach $ics as $property => $value}
		{if $property == 'components'}		
			<div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
  			{foreach $value as $key => $component}
  				<div class="col-sm-4">
					<div class="panel panel-default">
						<div class="panel-heading" role="tab" id="toggle-{$key}">
							<h4 class="panel-title">
							<a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapse-{$key}" aria-expanded="false" aria-controls="collapse-{$key}">
							({get_class($component)}) content[{$key}] &mdash; {date_format(date_create_from_format('Y-n-j-G-i-s', implode('-', $component->getProperty('DTSTART'))), 'Y-m-d H:i')} {$component->getProperty('SUMMARY')}
							</a>
							</h4>
						</div>
						<div id="collapse-{$key}" class="panel-collapse collapse" role="tabpanel" aria-labelledby="toggle-{$key}">
							<div class="panel-body">
								{if get_class($component) == 'vevent'}
									<dl>
										{foreach $veventProperties['unique'] as $propertyName}
											{$propertyValue = $component->getProperty($propertyName)}
											{if $propertyValue !== false}
												<dt>{$propertyName}</dt>
												<dd><pre>{if is_string($propertyValue)}{$propertyValue}{else}{var_dump($propertyValue)}{/if}</pre></dd>
											{/if}
										{/foreach}
									</dl>
								{else}
									<pre>{var_dump($component, true)}</pre>
								{/if}
							</div>
						</div>
					</div>
  				</div>
			{/foreach}
		{else}
			<p>{$property}: <pre>{var_dump($value, true)}</pre></p>
		{/if}
	{/foreach}
</div>

{/block}