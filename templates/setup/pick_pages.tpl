<form action="{devblocks_url}c=oauth&a=callback&provider={ServiceProvider_FacebookPages::ID}{/devblocks_url}" method="POST">
	<input type="hidden" name="mode" value="choosePages">
	<input type="hidden" name="view_id" value="{$view_id}">
	
	<ul style="list-style:none; padding-left:0px;">
	{foreach from=$pages item=page_name key=page_id}
		<li>
			<label>
				<input type="checkbox" name="page_ids[]" value="{$page_id}"> {$page_name}
			</label>
		</li>
	{/foreach}
	</ul>
	
	<button type="submit">{'common.save_changes'|devblocks_translate|capitalize}</button>
</form>