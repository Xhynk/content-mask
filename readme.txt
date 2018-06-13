=== Content Mask ===
Contributors: alexdemchak
Donate Link: http://ko-fi.com/reverendxhynk/
Tags: Embed, Domain Mask, Mask, Redirect, URL Mask
Requires at Least: 3.6
Tested Up To: 4.9.6
Stable tag: 1.4.2
Requires PHP: 5.4
Author URI: https://xhynk.com/
Plugin URL: https://xhynk.com/content-mask/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed or link to external content on any Page, Post, or Custom Post Type without the need to use complicated domain forwarding or domain masks.

== Description ==

[Read More About Content Mask](https://xhynk.com/content-mask/)

Content Mask allows you to embed any external content onto your own WordPress Pages, Posts, and Custom Post types. The end result is similar to setting up a [domain mask](http://www.networksolutions.com/support/what-is-web-forwarding-and-masking/) though the content is embedded into the front end of your website and is fully contained inside your WordPress permalink ecosystem.

With a simple 2-Step UI, you can embed any external content into your website. Simple enable the Content Mask on any Page, Post, or Custom Post type by clicking on the check mark; Then put in the URL that contains the content you want to embed.

- Using the Download (default) method will fetch the content from the Content Mask URL, cache it on your website, and replace the current page request with that content.

- Using the Iframe method will replace the current page request with a full width/height, frameless iframe containing the host URL. This method is ideal if you rely on whitelisted IP/domain names for certain functionality including serving scripts, styles, and images.

- Using the Redirect (301) method will simply redirect the visitor to the host URL.

Notes:

 - Please confirm you're allowed to utilize and embed the content before embedding any particular URL, don't Content Mask any content you don't have license to share or use.
 
 - Content embedded using the Download method is cached using the [WordPress Transients API](https://codex.wordpress.org/Transients_API) for 4 hours by default. If the content on the external URL is updated and you would like a fresh copy, you may just click the "Update" button on the Page, Post, or Custom Post Type to refresh the transient, or click the "Refresh" link in the Content Mask Admin panel. You may also change the cache expiration timer per page anywhere from "Never" to "4 weeks".

 - You may use the [Transients Manager](https://wordpress.org/plugins/transients-manager/) plugin to manage transients stored with the Download method. All Content Mask related transients contain the prefix "content_mask-" plus a stripped version of the Content Mask URL, such as "content_mask-httpxhynkcom".

[Read More About Content Mask](https://xhynk.com/content-mask/)

== Screenshots ==

1. Enable the Content Mask with the ? - Put in the URL of the content you would like to embed. Done! Optionally, choose a different method (Download, Iframe, or Redirect). If using the download method, you may also change the cache duration from never up to 4 weeks (you may refresh the cache at any point manually).
2. The Content Mask Admin Panel shows a list of all Content Mask pages/posts and their current settings. Quickly enable or disable the Content Mask with a single click on the Method icon. The cache may also be refreshed from this page. Only pages/posts that the current user can edit are displayed.
3. Notice that the URL has remained unchanged, but when Content Mask is enabled, it fully and seamlessly replaces all of the content on that permalink with the content from the Content Mask URL.

== Changelog ==
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