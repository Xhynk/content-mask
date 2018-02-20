=== Content Mask ===
Contributors: Alex Demchak
Donate Link: http://ko-fi.com/reverendxhynk/
Tags: Domain Mask, Content Mask, URL Mask, Embed, Redirect, 301, 301 Redirect, Iframe
Requires at Least: 3.5.1
Tested Up To: 4.9.4
Stable tag: 1.2
Author URI: https://github.com/Xhynk
Plugin URL: https://xhynk.com/content-mask/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed or link to external content on any Page, Post, or Custom Post Type without the need to use complicated domain forwarding or domain masks.

== Description ==

[Read More About Content Mask](https://xhynk.com/content-mask/)

Content Mask allows you to embed any external content onto your own WordPress Pages, Posts, and Custom Post types. The end result is similar to setting up a [domain mask](http://www.networksolutions.com/support/what-is-web-forwarding-and-masking/) though the content is embedded into the front end of your website.

With a simple 2-Step UI, you can download and embed external content into your website, just enable the Content Mask on any Page, Post, or Custom Post type and put in the URL that contains the content you want.

- Using the Download method will fetch the content from the host URL via a cURL request and embed it on the page. As of version 1.2, relative URLs in the `src`, `href`, and `action` attributes are replaced with the absolute URL equivalent.

- Using the Iframe method will replace the current page request with a full width/height, frameless iframe containing the host URL. This method is ideal if you rely on whitelisted IP/domain names for certain functionality including serving scripts, styles, and images.

- Using the Redirect (301) method will just redirect the visitor to the host URL.

Notes:

 - Please confirm you're allowed to utilize and embed the content before using any particular URL, don't Content Mask any content you don't have license to share or use.
 
 - Content embedded using the Download method is cached using the [WordPress Transients API](https://codex.wordpress.org/Transients_API) for 4 hours to prevent hammering the host URL with additional traffic. If the content is updated and you would like a fresh copy, you may just click the "Update" button on the Page, Post, or Custom Post Type to refresh the transient.

 - You may use the Transients Manager plugin to manage transients stored with the Download method. All Content Mask related transients contain the prefix "content_mask-" plus a stripped version of the Content Mask URL, such as "content_mask-httpxhynkcom".

[Read More About Content Mask](https://xhynk.com/content-mask/)

== Screenshots ==

1. Enable the Content Mask, set the Content Mask URL (the URL of the content you want to embed), and choose the Content Mask Method (Download, Iframe, or Redirect (301)).
2. See a list of all of the Content Masked pages, as well as their settings. Quickly enable/disable with a single click. Limited to posts/pages the current user can edit.
3. Notice the URL hasn't changed at all using the download or iframe method, but the content is 100% replaced on the front end with the Content Mask URL's content.

== Changelog ==

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