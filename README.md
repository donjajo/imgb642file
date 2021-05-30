```
=======
>>>>>>> 02fa473b99bba280365ac7c0ee07e927861eac13
=== ImageBase642File ===
Contributors: donjajo
Tags: save_post,convert image,image
Requires at least: 5.1
Tested up to: 5.7.2
Requires PHP: 7.1
Stable tag: trunk
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
```

An over-engineered-memory-safe search and replace base64 inline images to an uploaded URL when a post is saved.

# == Description ==

An over-engineered-memory-safe search and replace base64 inline images to an uploaded URL when a post is saved.

Converts this:
```
<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg==" alt="Red dot" />
```

To this:
```
<img src="https://site.com/wp-content/uploads/2021/05/post_title_id_no.png" alt="Red dot" />
```

I was working on an external editor to allow users to post on WordPress, this editor embeds images in base64 encoded format. This makes post contents too long to load on-page and also cannot be stored on buffer unless PHP is set to very high memory usage.

This plugin converts the encoded base64 inline images to a link by scrapping out the base64, decode, store to file and replace with the URL. The post content becomes very short. This is over-engineered to prevent PHP memory limit when trying to copy base64 encode to file. This plugin will perform each chunk copy on 76 characters, 76 bytes. This is the base64 line limit. There will never be an out of memory when copying these images. 
And, it is faster than using regex

# == Frequently Asked Questions ==

= Does this plugin support other post types?=

Currently, no. Only page and post are supported
