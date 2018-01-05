=== Content Mask ===
Contributors: Alex Demchak
Tags: Domain Mask, Content Mask, URL Mask
Requires at least: 3.5.1
Tested up to: 4.9.1
Stable tag: 1.1.4
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Link to external content without forwarding users and without setting up domain forwards with domain masks.

== Description ==

[Read More About Content Mask](https://xhynk.com/content-mask/)

This plugins allows you to embed content on a URL similar to using a Domain Forwarder with Domain Masking. With a simple UI, you can embed or full-page iframe content on any Page, Post, or Custom Post Type's permalink, or you can 301 redirect to it if you're not allowed to embed or download the content. Please confirm you're allowed to utilize and embed the content before you use any particular URL. To alleviate hammering external URLs with cURL requests, when using the Download method - the results are cached via the WordPress Transients API for 4 hours or until the Page, Post, or Custom Post Type is updated.

[Read More About Content Mask](https://xhynk.com/content-mask/)

== Screenshots ==

1. Enable the Content Mask, place the Content Mask URL (the URL of the content you want to embed), and choose the Content Mask Method (Download, Iframe, or Redirect (301)).

== Changelog ==
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