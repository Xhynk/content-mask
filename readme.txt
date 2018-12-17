=== Content Mask ===
Contributors: alexdemchak
Donate Link: https://www.paypal.me/xhynk/
Tags: Embed, Domain Mask, Mask, Redirect, Link
Requires at Least: 4.1
Tested Up To: 4.9.8
Stable tag: 1.7.0.5
Requires PHP: 5.4
Author URI: https://xhynk.com/
Plugin URL: https://xhynk.com/content-mask/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed any external content on a Page, Post, or Custom Post Type without the need to use complicated domain forwarding or domain masks.

== Description ==

[Read More & View Demos Here](https://xhynk.com/content-mask/)

= Embed Any Content Into Your WordPress Website =

Content Mask allows you to embed any external content onto your own WordPress Pages, Posts, and Custom Post Types. The end result is fairly similar to setting up a [Domain Mask](http://www.networksolutions.com/support/what-is-web-forwarding-and-masking/), but the content is embedded into the front end of your website and is fully contained inside your WordPress permalink ecosystem.

> *Example*: If you built a landing page on `landing-page-builder.com/your-landing-page/`, you can simply create a new Page on your website at `your-site.com/landing-page/` and paste in the URL of your landing page. The Content Mask plugin will then download and cache of copy of your landing page directly on your website, so any visitors that come to `your-site.com/landing-page/` will see the landing page you built. This allows you to keep all of your links integrated into your WordPress Website.

= Simple 2-Step UI =

With a simple 2-Step UI, you can embed any external content into your website without any complicated URL Forwarding, DNS Records, or `.htaccess` rules to mess with.

1. Just enable the Content Mask on any Page, Post, or Custom Post type by clicking on the check mark.

2. Then put in the URL that contains the content you want to embed.

It's that simple!

= Powerful Embedding and Redirect Options =

- Using the Download method (default) will fetch the content from the Content Mask URL, cache it on your website, and replace the current page request with that content. By default, this cache lasts 4 hours - but it can be changed anywhere from "Never Cache" all the way up to "Cache for 4 Weeks". Caching prevents the need for additional requests that slow down your site.

- Using the Iframe method will replace the current page request with a full width/height, frameless iframe containing the host URL. This method is ideal if the URL you want to embed won't serve scripts, styles, or images to other URLs or IP Addresses. If you use the Download Method, and links or images look broken, you can try the Iframe method instead.

- Using the Redirect (301) method will simply redirect the visitor to the host URL.

= Simple Integrated Vistor Tracking =

In the Content Mask admin panel, you can enable tracking for Content Masked pages. This will allow you to see how many visitors are viewing these links. This is ideal for when you need to track acquisition, such as on a Landing Page.

- [Views] shows how many times that Content Mask page has been viewed by anybody (even logged in users)
- [Non-User] shows how many times it's been viewed by visitors that are _not_ logged in to the website.
- [Unique] shows how many times it's been viewed by unique IP addresses. Note: IP addresses are one-way hashed and are not identifiable in any way.

= Creating a Content Masked Page =

https://www.youtube.com/watch?v=_H7IWFwmVfo?rel=0

= Using the Content Mask Admin Panel =

https://www.youtube.com/watch?v=5hEBMKSLHxI?rel=0

= Notes: =

 - Please confirm you're allowed to utilize and embed the content before embedding any particular URL, don't Content Mask any content you don't have license to share or use.
 
 - Content embedded using the Download method is cached using the [WordPress Transients API](https://codex.wordpress.org/Transients_API) for 4 hours by default. If the content on the external URL is updated and you would like a fresh copy, you may just click the "Update" button on the Page, Post, or Custom Post Type to refresh the transient, or click the "Refresh" link in the Content Mask Admin panel. You may also change the cache expiration timer per page anywhere from "Never" to "4 weeks".

 - You may use the [Transients Manager](https://wordpress.org/plugins/transients-manager/) plugin to manage transients stored with the Download method. All Content Mask related transients contain the prefix "content_mask-" plus a stripped version of the Content Mask URL, such as "content_mask-httpxhynkcom".

[Read More About Content Mask](https://xhynk.com/content-mask/)


== Installation ==

1. Upload the `content-mask` folder to your `/wp-content/plugins/` directory.

2. Activate the "Content Mask" plugin.

**How to Use:**

1. Edit (or Add) a Page, Post, or Custom Post Type.

2. Underneath the page editor, find the "Content Mask Settings" metabox.

3. Click the Checkmark on the left to enable Content Mask.

4. Paste a URL in the Content Mask URL field.

5. Choose a method: Download, Iframe, or Redirect (301).

6. Update (or Publish) the Page, Post or Custom Post Type.

7. That's all! When a user visits that Page, Post or Custom Post Type, they will instead see the content from the URL you have put in the Content Mask URL field.


== Frequently Asked Questions ==
= Can I send custom headers with the Download Method =

*No*. If this is a feature you would like implemented, please contact me.

= Can You Show the Header/Footer on Content Masked Pages? =

*No*. This is because of how page requests are processed. Using Content Mask will override the _entire_ page content on the front end.

= Can I Embed Multiple URLs on One Page? =

*No*. There's not currently a way to embed multiple URLs onto a single page. You can embed one URL on one page.

= Will Content Mask Overwrite My Page Content? =

*No*. Content Mask does *not* permanently alter anything on your website. The embedded content is only shown on the front-end. When you turn off Content Mask, any page content you had in the editor will still be there.

= Something Isn't Loading With the Download Method =

Some websites "whitelist" IP addresses or domains for scripts, images, and files to be accessed from. If that's the case, try using the iframe method instead.

= Something Isn't Loading with the Iframe Method =

Some websites don't allow themselves to be iframed at all. Please reach out to the webmaster for the content you wish to iframe.

= Links Aren't Working with the Iframe Method =

If your website is secured (with https://), make sure any links on the iframed page are secure as well, as most modern browsers don't allow insecure content (http://) to be loaded into a secure page or iframe.

== Screenshots ==

1. Enable the Content Mask with the Checkmark - Put in the URL of the content you would like to embed. Done! Optionally, choose a different method (Download, Iframe, or Redirect). If using the download method, you may also change the cache duration from never up to 4 weeks (you may refresh the cache at any point manually).
2. The Content Mask Admin Panel shows a list of all Content Mask pages/posts and their current settings. Quickly enable or disable the Content Mask with a single click on the Method icon. The cache may also be refreshed from this page. You may also enabled/disabled Vistor Tracking that shows how many times each Content Masked page has been viewed. Only pages/posts that the current user can edit are displayed.
3. The regular WordPress page content, without Content Mask on.
4. The same WordPress page with Content Mask enabled and set to https://example.com/. You can see the URL has remained the same but the content has been entirely replaced (on the front end only) by the content from https://example.com/

== Changelog ==
= 1.7.0.5 = 
* Fixed a bug causing `tel` and `mailto` to sometimes get caught in the relative URL replacement functions.

= 1.7.0.4 =
* Fixed issues with WordPress Versions < 4.9.0 in the Admin Panel
* Minor Additions to Admin Panel

= 1.7.0.3 =
* Fixed over/underwritten admin class filter.

= 1.7.0.2 =
* Fixed class-miss preventing admin CSS from applying on the admin panel in some cases.

= 1.7.0.1 =
* Updated HTTP Header Version Checker
* Added Viewing options to Admin Table Navigation

= 1.7 =
* Completely Overhauled the Content Mask Admin Panel
* Admin Panel now has a brand new, more user friendly design
* Admin Panel is now mobile friendly

= 1.6.0.3 =
* Moving plugin from SVN to GitHub for primary development
= 1.6.0.2 =
* Added Action Hooks `content_mask_iframe_header` and `content_mask_iframe_footer` to allow more dynamic control of the iframe method

= 1.6.0.1 =
* Under-The-Hood improvements for Admin Panel Options

= 1.6 =
* Removed test code from the Iframe Method accidentally introduced in 1.5.2.1
* Reorganized the Admin Panel
* Added ability to add custom JavaScript and CSS to Iframe and Download methods.
* Updated algorithm to replace relative URLs with the download method
* Included `wp_code_editor` in admin for scripts and styles fields.

= 1.5.2.1 =
* Cleaned Up a few functions in the admin
* Added some backwards compatibility for PHP 5.4

= 1.5.1 =
* Modified Admin Styles to use my preferred blue instead of green for highlighted/actionable items
* Added a new Advanced Option to send a User Agent HTTP Header when using the Download Method to assist with some stubborn authorization issues
* Added Feature Request link in the Admin Panel

= 1.5 =
* Started SCSS Conversion for admin.css file, broken into partials (still needs optimization)
* Modified Content Mask Admin Panel to load only 20 Content Masks, and subsequently load 20 more when scrolled to the bottom.
* Included Admin Help links above Content Mask Admin Panel
* Introduced an Admin Notice when a page is being overwritten with a Content Mask
* Introduced a "hacky" Admin Notice when a Gutenberg page is being overwritten with a Content Mask since Admin Notices are just hidden.
* Modified plugin structure with an includes file.
* Went back and better commented functions in core files to more closely follow best practices in documentation.

= 1.4.4.1 =
* Included the Site Title in the title tag when using the iframe method.

= 1.4.4 =
* Added an optional page tracking feature that tracks the number of visitors to each Content Masked page.
* Fixed a bug where the <title> tag wasn't showing up when using the iframe method.

= 1.4.3.1 =
* Removed the Cache Refresh option in the Content Mask Admin page for Masks set to Iframe and Redirect (since those methods aren't cacheable)
* Reverted the change made in 1.4.2 and moved the Page Processing function back to the template Redirect Hook. It was causing issues with homepage redirection.
* Password protection and removal have extraneous scripts has been added to this version of the Page Processing function as well.

= 1.4.3 =
* Content Masked pages now respect the Password Protected visibility status.
* When a Content Masked page is Password Protected, it shows the default page with the standard password form. Once the password is successfully submitted, the Content Mask will perform as usual.
* Removed superfluous and/or commented out code that's no longer used.

= 1.4.2 =
* To speed up Content Mask time, the page processing function has been moved to an earlier hook.
* Redundant URL Validity checks have been removed.
* Title has been linked in the Content Mask admin list for ease-of-use.
* Scripts and Styles that are hooked in an unorthodox manner are now killed before rendering a Content Masked page, this will speed up the page, prevent unwanted styles and scripts from being loaded, prevents JS errors from unrelated plugins being thrown in the console.

= 1.4.1 =
* Modified the Content Mask admin page table layout
* The Mask URL column is now linked and clickable.
* Cache Expiration column has been added.
* Cache may be refreshed by clicking on Refresh in the Cache Expiration column (shows on row hover).
* Edit and View columns have been removed.
* Edit and View links have been added to to the Title column (shows on row hover)

= 1.4 =
* Cache (WP Transient) Duration for the Download Method can now be controlled with common values from 1 hour to 4 weeks.

= 1.3 =
* Underthe hood improves with custom field variable extraction.
* Improved SVG icon clarity.
* Added Content Mask column to Page and Post edit lists which allows an at-a-glance preview of whether Content Mask is enabled, and which type; as well as allowing an Ajax button-press to enable or disable the Content Mask (like on the Content Mask overview admin page).

= 1.2.2 =
* Minor changes to prevent undefined variable and similar E_NOTICE level errors from appearing when debug mode was enabled.
* Removed dependency from external CSS in the admin, namely FontAwesome and Line Icons.
* Prevented irrelevant meta field checks when not strictly necessary.

= 1.2.1 =
* Behind the scenes improvement with the plugin name and label
* Addressed CSS issues with plugins that used the @keyframes name "check"
* Prevented the `process_page_request` function from firing in non singular instances. Post lists and archive pages were firing the first content mask they ran across.
* Replaced the $cm instance variable with a private variable to eliminate namespace conflicts

= 1.2 =
* Added Content Mask admin page that shows a list of all current Content Masks that the logged in user is allowed to edit. Each row displays all the pertinent info for each Content Mask, and allows a one-click interface to disable or enable it.
* Using the Download method will now replace all relative URLs from the Content Mask URL with an absolute URL. This includes all `src`, `href` and `action` attributes. Protocol relative and existing absolute URLs are unaffected, but this should allow for significant improvements to consistency, especially with local form actions and local image & script libraries.
* Some fluff code has been removed from the front end of the Iframe method.

= 1.1.4.2 =
* Forgot to remove class methods that were no longer in use, which triggered E_NOTICE errors in some sites.

= 1.1.4.1 =
* Content Mask URL's without a protocol have `http://` added to them, since not all sites are secure yet. However, if your site is secure, it won't display `http://` iframes. Iframe method now checks if your site is secured with ssl, and if so force updates the Content Mask URL's protocol to `https://`. If the content still is blank, it's because the iframe'd site is insecure and wouldn't show up either way. 

= 1.1.4 =
* If other (namely really large) metaboxes were hooked in, Content Mask Settings were hard to see. Moved inline CSS and JS to separate files and improved the design of the metabox to make it stand out much more when buried deeply in the admin.

= 1.1.3 =
* Elegant Theme's "Bloom" was interfering and still being hooked. It's now been forcefully unhooked on Content Mask pages (regardless of content displayed)

= 1.1.2 =
* Made Content Mask Method an array to allow for easier updating/additions in the future

= 1.1.1 =
* Provided better URL validation on the front end

= 1.1.0 =
* Replaced `get_page_content` functions cURL methods with integrated WP HTTP API methods instead
* Added custom sanitization functions for text (URL) inputs, select boxes, and checkboxes.
* Escaped post meta field values when returned in the admin and front-end.

= 1.0.1 =
* Initial Public Repository Release