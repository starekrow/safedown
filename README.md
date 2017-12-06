# Safedown

This converts text to HTML using a restricted subset of [markdown][1]. No inline 
HTML is allowed at all. Links are supported but disabled by default. Designed 
to be safe with unrestricted user input.

[1]: https://daringfireball.net/projects/markdown/syntax

### Using Safedown

Instantiate and call `text()`:

	$sd = new Safedown();
	echo $sd->text( "Safedown is *awesome*." );

Result:
	<p>Safedown is <em>awesome</em>.</p>

### Links

There is special handling for links found in the markdown. By default, both
automatic and explicit links are mangled to prevent clicking on them (also
to prevent crawlers from linking to every darn thing your users put in their
text).

If you wish, you can allow some or all links to be rendered as anchor tags. 
Supply a `filterLinks` property in the options when you construct your Safedown
instance, like so:

	$sd = new Safedown( [
		"filterLinks" => function($l) { return true; }
	] );

The function will receive an array with the following values:
* `url` - URL to link to
* `text` - text to draw for link (before markdown conversion)
* `title` - title to use for link (may be missing or null)
* `click` - javascript to run when anchor is clicked (`onclick` text)

The function should return a modified array, OR `false` to perform the default 
action (mangle/deactivate the link) or `true` to allow the link to be used 
with no changes.

