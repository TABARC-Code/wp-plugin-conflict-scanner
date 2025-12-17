# IdiotsGuide  
WP Plugin Conflict Scanner

This is for the version of me who is tired, slightly angry at WordPress, and does not have three hours to play "disable every plugin and see what breaks".

No insult implied. This is a guide for humans with limited patience.

## The situation this is built for

You know this story.

- Site was fine yesterday.  
- Today, something is off.  
- Layout broken. Content mangled. Random white screens.  
- The client swears they did not touch anything.  
- You suspect a plugin conflict.

The default debugging method is:

1. Disable every plugin.  
2. Turn them back on one by one.  
3. Hope you remember what was active.  
4. Lose most of your afternoon.

This plugin does not replace proper debugging, but it at least tells you which plugins to glare at first.

## Where to find it

After activating the plugin:

1. Log into the WordPress admin.  
2. Go to Tools.  
3. Click Plugin Conflict Scanner.  

If you cannot see it:

- You might not have permission to manage options.  
- Someone might have hidden the Tools menu because they thought that was a good idea.  

## What you will see

The page has two main sections that matter.

### 1. Hotspot hooks

This is a table of WordPress hooks that commonly cause trouble.

Things like:

- `the_content`  
- `wp_head`  
- `template_redirect`  
- `wp_enqueue_scripts`  

For each hook, you see:

- The hook name.  
- A list of plugins that are attached to it.  
- The priorities those plugins use.  

Interpretation in plain language:

- If a hook has one plugin attached, it is probably not the source of conflict.  
- If a hook has five plugins attached, all trying to modify content or HTML, that is a crowd.  

This does not guarantee a bug. It tells you where code from multiple plugins collides.

### 2. Shortcodes with multiple owners

Shortcodes are little landmines.

If two plugins both think `[gallery]` is their shortcode, you get inconsistent output.

This section:

- Lists shortcodes where more than one plugin appears to own the callback.  
- Shows you which plugins those are.  

If your problem only appears on pages that use a particular shortcode, this list is your map.

## How to use this when debugging

Here is the calm, simple way to approach it.

### Step 1  
Visit Tools  
Plugin Conflict Scanner.

### Step 2  
Look at the Hotspot hooks table.

Ask yourself:

- Is `the_content` used by a suspicious number of plugins?  
- Is `wp_head` loaded with random plugins that have no business touching markup?  
- Is `template_redirect` being touched by things that do routing or redirects?  

Make a short list of the worst offenders.

### Step 3  
Look at the Shortcodes table.

If your issue shows up only when a particular shortcode is on the page:

- Find that shortcode in the table.  
- See which plugins appear next to it.  
- Those plugins are your first toggle candidates.  

### Step 4  
Start testing in a safe environment.

On staging, or at least outside peak traffic:

- Disable one of the suspected plugins.  
- Refresh the broken page.  
- See if the issue goes away.  

Repeat until you find the pair or trio that cause the problem.

This plugin does not switch anything off for you. You still flip the switches. It just tells you which ones to reach for first.

## Things to be careful about

Yes, this is still WordPress. There are traps.

### 1. Correlation is not proof

Just because two plugins share a hook does not mean they are conflicting.

The scanner is a hint system, not a psychic. Use judgement.

### 2. Some plugins live everywhere

Big plugins hook into lots of places. Security plugins, membership plugins, shop plugins.

It is normal for those to appear several times. That does not automatically make them guilty, but they are more likely to clash with others.

### 3. Shortcodes can be registered late

If a shortcode is only registered in the front end or only in certain templates, you might need to:

- Visit a page that uses it.  
- Then reload the scanner page so it sees it.  

This scanner is not a time traveller. It sees whatever WordPress has registered during the current request.

### 4. This does not replace a staging site

Do not use this as an excuse to debug live on a production site with paying customers watching.

Use it to narrow down suspects. Then do the real work on a clone.

## Simple mental model

If you are brain tired, use this.

- Broken content or markup?  
  Look at `the_content`, `wp_head`, `wp_footer`, `wp_enqueue_scripts`.

- Broken routing or redirects?  
  Look at `init` and `template_redirect`.

- Shortcodes behaving weirdly?  
  Look at the shortcode table and see which plugins own them.

If more than two plugins show up around the same hook or shortcode, that is your candidate pool.

## When this plugin is not helpful

There are situations where this will not save you.

- Pure CSS conflicts.  
  Two plugins both styling the same class name. This scanner will not see that.

- Logic bugs inside a single plugin.  
  If the problem is one bad update, not a conflict, you still have to deal with the plugin itself.

- Hosting level issues.  
  Timeouts, memory limits, server crashes. That is outside the scope.

This tool is for logical conflicts between plugins that are all trying to modify the same things. Nothing more. Nothing less.

## Final thoughts

If every plugin author wrote perfect, non intrusive code, you would not need this.

They do not. So you do.

Use Plugin Conflict Scanner as a way to spend less time stabbing in the dark and more time deliberately breaking things in a controlled fashion, which is basically what debugging is anyway.

Run it, note the suspects, then go do the real work.
LICENSE
text
Copy code
WP Plugin Conflict Scanner
Copyright (c) 2025 TABARC-Code

This program is free software. You can redistribute it and or modify it under the terms of the
GNU General Public License as published by the Free Software Foundation, either version 3 of
the License, or any later version.

This program is distributed in the hope that it will be useful, but without any warranty,
without even the implied warranty of merchantability or fitness for a particular purpose.

You should have received a copy of the GNU General Public License along with this program.
If not, see:
https://www.gnu.org/licenses/gpl-3.0.html
