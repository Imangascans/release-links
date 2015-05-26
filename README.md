# release-links
WordPress plugin to add and display links below posts

You can add a URL when editing a post. The URL gets displayed below the post.

You need to do your own styling. The basic format is:
  <div class="links">
    <span class="links">Links:</span>
    <ul>
      <li><a class="release_link" href="{the url}" target="_blank">{the type}</a></li>
    </ul>
  </div>

Before usage, you can define the link types by modifying `protected static $default_types`
