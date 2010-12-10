#Google Reader Library for ExpressionEngine
**License:** BSD License
**Version:** 1.0.1 (2010-10-25)

The Google Reader plugin allows you to display your shared, starred, and unread items from google reader within your site.

This plugin requires that you have an account with google reader.

##Using the Plugin Ñ Parameters
The main tag for the plugin, *{exp:google_reader}*, supports a few parameters that allow you to customize the behavior:

* **type**: Type of items to display. Valid values are "shared", "starred", or "all".
* **limit**: Maximum number of items to display. Default is 20.
* **refresh**: How long (in minutes) to cache responses before checking for updates. Default is 15 minutes.

To access a user's feed you have two options. The first is using the account's ID, as opposed to a email/password. Google exposes a user's shared items as a public Atom feed that anyone can access assuming they know the user ID (or said user sends them the link). If you need more than just shared items, use the email/password method.

To get the ID: log into Google Reader, go to your shared items, click "See your shared items page in a new window." You'll see a string of numbers in the URL you are taken to. If you use this method you need to provide the ID to the plugin:

* **id**: The public ID of the google user. This can only be used with shared items as it is the only feed that is publicly accessible.

If you use the email/password method you have access to all feeds for a user (this is the required method if you want to show starred or all feeds). Using this method requires these parameters:

* **email**: Email address of user to show items for.
* **password**: Password of user to show items for.

##Using the Plugin Ñ Variables
**Single Variables**

* **{lastupdated}**: Last updated date for the entire feed.

**Pair Variables**
There is one pair variable for the plugin, *{item}*, which represents a single item within the feed. Within this paired variable there are single variables representing the data for that individual item:

* **{title}**: Title of the feed item.
* **{url}**: URL to this feed item.
* **{published}**: Publish date for the feed item.
* **{updated}**: Last updated date for the feed item.
* **{summary}**: Summary for the feed item.

##Using the Plugin Ñ Anonymous Calls
Well, more *call* than *calls*. As stated above, the only feed you can access without providing an email/password is the shared item feed. Once you've looked up your ID you can load your shared items (for the sake of example, the last 10) like this:

	{exp:google_reader id='12006118737470781753' limit='10'}
	<div class="date">Updated on {lastupdated format="%F %d, %Y"}</div>
	<ul id="links">
	{items}
	<li><a href="{url}">{title}</a></li>
	{/items}
	</ul>
	{/exp:google_reader}

##Using the Plugin Ñ Logged-in Call Examples
Logging in to Google Reader using your email and password gives you access to feeds beyond the shared item feed Ñ specifically the starred items and all items feeds. An example call of logging in and showing your most 20 most recent starred items, including their summaries, is as follows:

	{exp:google_reader email='someone@someisp.com' password='arealpassword' limit='20' type='starred'}
	<div class="date">Updated on {lastupdated format="%F %d, %Y"}</div>
	<ul id="links">
	{items}
	<li>
	<a href="{url}">{title}</a>
	<p>{summary}</p>
	</li>
	{/items}
	</ul>
	{/exp:google_reader}

If you wanted to similarly output the most recent items in your feed, without limiting yourself to starred or shared, you'd do the following:

	{exp:google_reader email='someone@someisp.com' password='arealpassword' limit='20' type='all'}
	<div class="date">Updated on {lastupdated format="%F %d, %Y"}</div>
	<ul id="links">
	{items}
	<li>
	<a href="{url}">{title}</a>
	<p>{summary}</p>
	</li>
	{/items}
	</ul>
	{/exp:google_reader}