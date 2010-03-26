
var urnotificationPlugin = {
	editorTextArea: $('message_new'),
	
	parsePost:function()
	{
		var str = $('message_new').value;
		var regexp = new RegExp("@(\\w+)","ig");
		var messageText = "<br/>These users will be notified by email if they are valid users and wish to be notified: <br/><b>";
		var userNameArray = new Array();

		while((m = regexp.exec(str)) != null)
		{
			userNameArray.push(m[1]);
		}
		userNameArray = userNameArray.uniq();

		if(userNameArray.length > 0)
		{
			document.getElementById('urnotificationMessage').innerHTML = messageText + userNameArray.join('</b>, <b>') + "</b><br/><br/>";
		}
		else
		{
			document.getElementById('urnotificationMessage').innerHTML = "";
		}
	},

	registerEvents:function()
	{
		Event.observe(urnotificationPlugin.editorTextArea, 'keyup', urnotificationPlugin.parsePost);
		Event.observe(urnotificationPlugin.editorTextArea, 'mouseup', urnotificationPlugin.parsePost);
	},

	unregisterEvents:function()
	{
		Event.stopObserving(urnotificationPlugin.editorTextArea, 'keyup', urnotificationPlugin.parsePost);
		Event.stopObserving(urnotificationPlugin.editorTextArea, 'mouseup', urnotificationPlugin.parsePost);
	},
	
	toggleParsePost:function(cb)
	{
		var dynamicParsePost = cb.checked;
		
		if(dynamicParsePost)
			urnotificationPlugin.registerEvents();
		else
			urnotificationPlugin.unregisterEvents();
	},

	init:function(){
		
		var tableCell = $$('div.messageEditor')[0].ancestors()[0];
		var el = new Element('div',{class:'smalltext'});
		el.innerHTML = '<input type="checkbox" checked="checked" onclick="urnotificationPlugin.toggleParsePost(this)" value="1"> parse usernames as I type';
		tableCell.insert({bottom:el});
		
		el = new Element('div', {id:'urnotificationMessage', class:'smalltext'});
		var tableCell = $$('div.messageEditor')[0].ancestors()[0];
		tableCell.insert({bottom:el});
		
		urnotificationPlugin.registerEvents();
	}
};

urnotificationPlugin.init();
