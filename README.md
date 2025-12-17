# wp-plugin-conflict-scanner
Tries to show which plugins are fighting over hooks and shortcodes, so I do not have to play "disable everything" for three hours.  or usual till early hours.....

<p align="center">
  <img src=".branding/tabarc-icon.svg" width="180" alt="TABARC-Code Icon">
</p>

# WP Plugin Conflict Scanner

Plugin conflicts are the WordPress equivalent of unexplained noises in the loft. You know something is wrong. You just cannot see it. The usual fix is to disable half the site and hope for the best.

This plugin does not magically fix conflicts. It does something more realistic.

It shows me where plugins are piling onto the same hooks and shortcodes, so I can stop guessing and start blaming the right suspects.

## What it does

This version focuses on visibility, not automation.

It:

- Scans a set of high risk hooks and shows which plugins are attached to each one  
  Hooks like:
  - `the_content`
  - `the_excerpt`
  - `wp_head`
  - `wp_footer`
  - `init`
  - `template_redirect`
  - `wp_enqueue_scripts`
  - `admin_enqueue_scripts`
  - `plugins_loaded`
  - `widgets_init`

- For each hotspot hook, lists:
  - Which plugins have callbacks attached
  - Their plugin file
  - The priorities they use

- Scans registered shortcodes and tries to work out which plugin owns each one  
  Then flags shortcodes where more than one plugin seems to be involved

What you get is a single page of "here is where multiple plugins are touching the same things that matter most".

No more blind toggling without at least a hint.

## What it does not do

Just so we are clear.

It does not:

- Disable plugins for you  
- Resolve conflicts automatically  
- Rewrite priorities or unhook callbacks  
- Read your debug log  
- Promise complete coverage  

This is a map, not a medic. You still debug. This just stops you wandering in the dark.

## Requirements

- WordPress 6.0 or newer  
- PHP 7.4 or newer, PHP 8 recommended  
- Ability to access Tools and manage options  

Reflection needs PHP that is not ancient.

## Installation

Clone or download the repository.

```bash
git clone https://github.com/TABARC-Code/wp-plugin-conflict-scanner.git
Drop it into:

text
Copy code
wp-content/plugins/wp-plugin-conflict-scanner
Activate it in the admin under Plugins.

Then:

Go to Tools

Click Plugin Conflict Scanner

If you do not see it, check your capabilities or whether some other plugin decided to "simplify" your menu.

How to read the results
Hotspot hooks
At the top you will see a table of hooks and the plugins that attach to them.

If you see:

the_content with one plugin attached
Calm.

the_content with five plugins all glued to it at various priorities
That is a potential stress test waiting to happen.

Use this table as:

A list of plugins that are likely to interfere with output

A way to decide which ones to temporarily disable while debugging

A hint for where to add logging or tracing if you are that way inclined

Shortcodes with multiple owners
Shortcodes are a classic conflict vector.

Two plugins both deciding that [gallery] or [slider] is theirs leads to fun outcomes.

This section:

Lists only shortcodes where more than one plugin appears to own the callback

Gives you plugin name and plugin file for each one

If you have broken layouts around a given shortcode, start with this list.

Limitations, on purpose
This scanner is deliberately honest about its own limits.

It only scans the hooks you see listed in the code

It only sees shortcodes that are registered in the current request

It resolves file paths by Reflection, which means:

Anonymous functions and closures still resolve fine

Some weird dynamic callbacks may not

If a plugin does something deeply strange, it may slip past. That is the nature of WordPress.

Suggested workflow
Real world usage looks like this.

Site is misbehaving.
Content mangled. Layout broken. Something is off.

You suspect a plugin conflict.
Because of course you do.

Open Tools
Plugin Conflict Scanner.

Look at hooks first.
Focus on:

the_content

wp_head

template_redirect

wp_enqueue_scripts

Note the plugins stacked on those hooks.
These are the ones you test toggling first, not the innocent ones lurking elsewhere.

Then check shortcodes.
If the issue appears only when a shortcode is present, and you see multiple plugins listed for that shortcode, you know exactly where to start switching things off.

This will not replace proper debugging. It just saves you a few laps around the deactivate and reload circus.

Safety notes
The scanner is read only.

It does not change any plugin behaviour

It does not edit hooks

It does not write to the database during scans

The only stateful things are:

WordPress itself having loaded plugins and registered hooks

Your own patience level while staring at the tables

You can run this on a live site safely. It will not execute anything extra, it just inspects what is already there.

Roadmap
Things that might show up later if I keep being annoyed enough:

Script and style handle ownership mapping by plugin

Very simple error correlation with recently activated plugins

Export of a "plugin to hook" matrix for diffing over time

Filters to highlight plugins that are attached to many critical hooks at once

Things I probably will not add:

Automatic conflict resolution

A big scary button that unhooks half your site

Fancy graphs

If you want a pretty chart, you can always dump the data and feed it to something else.
