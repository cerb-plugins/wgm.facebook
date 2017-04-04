<html>
<head>
	<link type="text/css" rel="stylesheet" href="{devblocks_url}c=resource&p=cerberusweb.core&f=css/cerb.css{/devblocks_url}?v={$smarty.const.APP_BUILD}">
</head>

<body>
<form action="{devblocks_url}c=oauth&a=callback&provider={ServiceProvider_FacebookPages::ID}{/devblocks_url}" method="POST">
	<input type="hidden" name="mode" value="choosePage">
	<input type="hidden" name="view_id" value="{$view_id}">
	
	<b>Select page:</b>
	
	<ul style="list-style:none; padding-left:0px;">
	{foreach from=$pages item=page_name key=page_id}
		<li>
			<label>
				<input type="radio" name="page_id" value="{$page_id}"> {$page_name}
			</label>
		</li>
	{/foreach}
	</ul>
	
	<button type="submit">{'common.continue'|devblocks_translate|capitalize}</button>
</form>
</body>
</html>
