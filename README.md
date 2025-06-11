# Clean-Duplicate-Meta-Keys

<h2>Background</h2>
<p></p>On a client website using <a href="https://theeventscalendar.com/" target="_blank">The Events Calendar</a> plugin, Inverse Paradox identified a performance issue caused by recurring events (<a href="https://theeventscalendar.com/knowledgebase/creating-a-recurring-event-2/" target="_blank">documentation</a>). Specifically, recurring events were generating duplicate meta keys within the WordPress database. This led to excessive memory usage during event-related queries, ultimately causing the site to crash.</p>

<h2>Solution</h2>
<p></p>To address this, we developed a lightweight plugin that routinely scans and removes duplicate meta key entries from the WordPress database. The plugin identifies records in the postmeta table that have the same meta_key and meta_value associated with a post, and deletes all duplicates to maintain site performance and stability.</p>

<h2>Instructions</h2>
<p>After installation, the plugin settings are located under:</p>
<h3>Tools > Clean Meta Keys</h3>
<p>From the settings page, you can either schedule regular cleanups or run the process manually.</p>

<h3>To Schedule Cleanups:</h3>
<ol>
<li>Enter a value in the <b>“Run cleanup every X days”</b> field.</li>
<li>Click <b>“Update Schedule”</b> to activate the schedule.</li>
</ol>

<h3>To Run a Manual Cleanup:</h3>
<ul>
<li>Click <b>“Run Cleanup Now”</b> to initiate the process immediately.</li>
</ul>

<p>Cleanup results will appear in the <b>Recent Logs</b> section of the settings page.</p>

<h3>⚠️ Important Note</h3>
<p></p>This plugin is intended as a base tool for developers experiencing similar performance issues related to duplicate meta keys. Because it modifies the database directly, it may pose a risk depending on your site’s specific configuration and active plugins.</p>

<p><b>We strongly recommend testing this plugin in a development or staging environment before deploying it to a live site.</b></p>
