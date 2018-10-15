# Content Mask

Content Mask is a WordPress plugin that allows you to embed any external content on a Page, Post, or Custom Post Type without the need to use complicated domain forwarding or domain masks.


## Links
 - [Plugin Homepage](https://xhynk.com/content-mask/)
 - [WordPress Repository & Download Page](https://wordpress.org/plugins/content-mask/)
 - [Donate](https://www.paypal.me/xhynk/)


## About Content Mask

Content Mask allows you to embed any external content onto your own WordPress Pages, Posts, and Custom Post Types. The end result is fairly similar to setting up a [Domain Mask](http://www.networksolutions.com/support/what-is-web-forwarding-and-masking/), but the content is embedded into the front end of your website and is fully contained inside your WordPress permalink ecosystem.

**Example**: If you built a landing page on `external-site.com/your-landing-page/`, you can simply:
1. Create a new page on your website at `your-wp-site.com/landing-page/` .
2. Paste the URL of your landing page into the Content Mask URL metabox.

The Content Mask plugin will then download and cache of copy of your landing page directly on your website, so any visitors that come to `your-wp-site.com/landing-page/` will see the landing page you built. This allows you to keep all of your links integrated into your WordPress Website directly.

## Simple 2-Step UI

With a simple 2-Step UI, you can embed any external content into your website without any complicated URL Forwarding, DNS Records, or `.htaccess` rules to mess with.
1. Click on the ✅ icon to enable Content Mask when editing any Page, Post, or Custom Post type.
2. Paste the URL that contains the content you want to embed into the Content Mask URL field.

## Rename a file

You can rename the current file by clicking the file name in the navigation bar or by clicking the **Rename** button in the file explorer.

## Powerful Embedding and Redirect Options

- Using the Download method (default) will fetch the content from the Content Mask URL, cache it on your website, and replace the current page request with that content. By default, this cache lasts 4 hours - but it can be changed anywhere from "Never Cache" all the way up to "Cache for 4 Weeks". Caching prevents the need for additional requests that slow down your site.

- Using the Iframe method will replace the current page request with a full width/height, frameless iframe containing the host URL. This method is ideal if the URL you want to embed won't serve scripts, styles, or images to other URLs or IP Addresses. If you use the Download Method, and links or images look broken, you can try the Iframe method instead.

- Using the Redirect (301) method will simply redirect the visitor to the host URL.

## Simple Integrated Vistor Tracking

In the Content Mask admin panel, you can enable tracking for Content Masked pages. This will allow you to see how many visitors are viewing these links. This is ideal for when you need to track acquisition, such as on a Landing Page.

- **Views** shows how many times that Content Mask page has been viewed by anybody (even logged in users)
- **Non-User** shows how many times it's been viewed by visitors that are _not_ logged in to the website.
- **Unique** shows how many times it's been viewed by unique IP addresses. Note: IP addresses are one-way hashed and are not identifiable in any way.

## Creating a Content Masked Page
[![Creating a Content Masked Page](https://img.youtube.com/vi/5hEBMKSLHxI/0.jpg)](https://www.youtube.com/watch?v=5hEBMKSLHxI?rel=0)
## Using the Content Mask Admin Panel
[![Creating a Content Masked Page](https://img.youtube.com/vi/_H7IWFwmVfo/0.jpg)](https://www.youtube.com/watch?v=_H7IWFwmVfo?rel=0)

## Notes
 - Please confirm you're allowed to utilize and embed the content before embedding any particular URL, don't Content Mask any content you don't have license to share or use.
 
 - Content embedded using the Download method is cached using the [WordPress Transients API](https://codex.wordpress.org/Transients_API) for 4 hours by default. If the content on the external URL is updated and you would like a fresh copy, you may just click the "Update" button on the Page, Post, or Custom Post Type to refresh the transient, or click the "Refresh" link in the Content Mask Admin panel. You may also change the cache expiration timer per page anywhere from "Never" to "4 weeks".

 - You may use the [Transients Manager](https://wordpress.org/plugins/transients-manager/) plugin to manage transients stored with the Download method. All Content Mask related transients contain the prefix "content_mask-" plus a stripped version of the Content Mask URL, such as "content_mask-httpxhynkcom".

## How to Use

1. Edit (or Add) a Page, Post, or Custom Post Type.
2. Underneath the page editor, find the "Content Mask Settings" metabox.
3. Click the Checkmark on the left to enable Content Mask.
4. Paste a URL in the Content Mask URL field.
5. Choose a method: Download, Iframe, or Redirect (301).
6. Update (or Publish) the Page, Post or Custom Post Type.
7. That's all! When a user visits that Page, Post or Custom Post Type, they will instead see the content from the URL you have put in the Content Mask URL field.
## Frequently Asked Questions
1. #### Can I send custom headers with the Download Method?
	*No*. If this is a feature you would like implemented, please contact me.

2. #### Can You Show the Header/Footer on Content Masked Pages? 
	*No*. This is because of how page requests are processed. Using Content Mask will override the _entire_ page content on the front end.

3. #### Can I Embed Multiple URLs on One Page?
	*No*. There's not currently a way to embed multiple URLs onto a single page. You can embed one URL on one page.

4. #### Will Content Mask Overwrite My Page Content?
	*No*. Content Mask does *not* permanently alter anything on your website. The embedded content is only shown on the front-end. When you turn off Content Mask, any page content you had in the editor will still be there.

5. #### Something Isn't Loading With the Download Method
	Some websites "whitelist" IP addresses or domains for scripts, images, and files to be accessed from. If that's the case, try using the iframe method instead.

6. #### Something Isn't Loading with the Iframe Method
	Some websites don't allow themselves to be iframed at all. Please reach out to the webmaster for the content you wish to iframe.

7. #### Links Aren't Working with the Iframe Method
	If your website is secured (with https://), make sure any links on the iframed page are secure as well, as most modern browsers don't allow insecure content (http://) to be loaded into a secure page or iframe.