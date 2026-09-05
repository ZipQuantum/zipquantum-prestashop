{*
 * ZipQuantum Smart Links and QR Codes integration for PrestaShop.
 *
 * @author Xaere
 * @copyright 2026 Xaere
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *}

{if !empty($zq_state.identity_mismatch)}
  <div class="alert alert-danger">
    <strong>{l s='This shop appears to have been moved or cloned.' d='Modules.Zipquantum.Admin'}</strong>
    {l s='Synchronization is blocked until you move the installation, reconnect, or create a new local installation.' d='Modules.Zipquantum.Admin'}
  </div>
{/if}

<div class="zqps-shell" data-zq-ajax-url="{$zq_ajax_url|escape:'htmlall':'UTF-8'}">
  <div class="zqps-heading">
    <img src="{$zq_logo_url|escape:'htmlall':'UTF-8'}" width="40" height="40" alt="ZipQuantum">
    <div>
      <h2>{l s='ZipQuantum Smart Links & QR Codes' d='Modules.Zipquantum.Admin'}</h2>
      <p>{l s='Privacy-safe links for products, categories and promotions.' d='Modules.Zipquantum.Admin'}</p>
    </div>
  </div>

  <section class="panel zqps-panel">
    <h3>{l s='1. Account connection' d='Modules.Zipquantum.Admin'}</h3>
    {if $zq_connected}
      <p><span class="zqps-badge zqps-badge-success">{l s='Connected' d='Modules.Zipquantum.Admin'}</span>
        {$zq_context.account.name|default:''|escape:'htmlall':'UTF-8'}
      </p>
      <button type="button" class="btn btn-default zqps-oauth" data-intent="reconnect">
        {l s='Reconnect ZipQuantum' d='Modules.Zipquantum.Admin'}
      </button>
      {if !empty($zq_state.identity_mismatch)}
        <button type="button" class="btn btn-primary zqps-oauth" data-intent="move">
          {l s='Move existing installation' d='Modules.Zipquantum.Admin'}
        </button>
        <button type="button" class="btn btn-default zqps-action" data-action="newInstallation">
          {l s='Create a new installation' d='Modules.Zipquantum.Admin'}
        </button>
      {/if}
      <button type="button" class="btn btn-link text-danger zqps-action" data-action="disconnect">
        {l s='Disconnect' d='Modules.Zipquantum.Admin'}
      </button>
    {else}
      <button type="button" class="btn btn-primary zqps-oauth" data-intent="connect">
        {l s='Connect or create a ZipQuantum account' d='Modules.Zipquantum.Admin'}
      </button>
    {/if}
    <p class="zqps-message" aria-live="polite"></p>
  </section>

  <form method="post" action="{$zq_action_url|escape:'htmlall':'UTF-8'}">
    <section class="panel zqps-panel">
      <h3>{l s='2. Routing and automatic synchronization' d='Modules.Zipquantum.Admin'}</h3>
      <div class="form-group">
        <label for="zqps-subdomain">{l s='Managed zq.tn subdomain' d='Modules.Zipquantum.Admin'}</label>
        <input id="zqps-subdomain" class="form-control" type="text" name="ZQPS_MANAGED_SUBDOMAIN"
               value="{$zq_settings.managed_subdomain|escape:'htmlall':'UTF-8'}" placeholder="mybrand">
      </div>
      <div class="form-group">
        <label for="zqps-domain">{l s='Verified custom domain' d='Modules.Zipquantum.Admin'}</label>
        <input id="zqps-domain" class="form-control" type="text" name="ZQPS_CUSTOM_DOMAIN"
               value="{$zq_settings.custom_domain|escape:'htmlall':'UTF-8'}" placeholder="go.example.com">
      </div>
      <div class="form-group">
        <label for="zqps-promotion-dest">{l s='Promotion destination path' d='Modules.Zipquantum.Admin'}</label>
        <input id="zqps-promotion-dest" class="form-control" type="text" name="ZQPS_PROMOTION_DEST"
               value="{$zq_settings.promotion_destination|escape:'htmlall':'UTF-8'}" placeholder="/order">
      </div>
      <div class="checkbox">
        <label>
          <input type="checkbox" name="ZQPS_AUTO_CREATE" value="1" {if $zq_settings.auto_create}checked{/if}>
          {l s='Automatically create Smart Links when selected object types are added or updated' d='Modules.Zipquantum.Admin'}
        </label>
      </div>
      <div class="zqps-checks">
        <label><input type="checkbox" name="ZQPS_OBJECT_TYPES[]" value="product"
          {if in_array('product', $zq_settings.object_types)}checked{/if}> {l s='Products' d='Modules.Zipquantum.Admin'}</label>
        <label><input type="checkbox" name="ZQPS_OBJECT_TYPES[]" value="category"
          {if in_array('category', $zq_settings.object_types)}checked{/if}> {l s='Categories' d='Modules.Zipquantum.Admin'}</label>
        <label><input type="checkbox" name="ZQPS_OBJECT_TYPES[]" value="promotion"
          {if in_array('promotion', $zq_settings.object_types)}checked{/if}> {l s='Promotions and coupons' d='Modules.Zipquantum.Admin'}</label>
      </div>
      <button type="submit" name="submitZipquantumSettings" class="btn btn-primary">
        {l s='Save settings' d='Admin.Actions'}
      </button>
    </section>
  </form>

  <div class="zqps-grid">
    <section class="panel zqps-panel">
      <h3>{l s='3. Create or attach one Smart Link' d='Modules.Zipquantum.Admin'}</h3>
      <div class="form-group">
        <label for="zqps-object-type">{l s='Object type' d='Modules.Zipquantum.Admin'}</label>
        <select id="zqps-object-type" class="form-control">
          <option value="product">{l s='Product' d='Modules.Zipquantum.Admin'}</option>
          <option value="category">{l s='Category' d='Modules.Zipquantum.Admin'}</option>
          <option value="promotion">{l s='Promotion or coupon' d='Modules.Zipquantum.Admin'}</option>
        </select>
      </div>
      <div class="form-group">
        <label for="zqps-object-id">{l s='PrestaShop object ID' d='Modules.Zipquantum.Admin'}</label>
        <input id="zqps-object-id" class="form-control" type="number" min="1">
      </div>
      <button type="button" class="btn btn-primary zqps-object" data-action="sync">
        {l s='Create or synchronize managed link' d='Modules.Zipquantum.Admin'}
      </button>
      <hr>
      <div class="form-group">
        <label for="zqps-link-id">{l s='Existing Smart Link ID' d='Modules.Zipquantum.Admin'}</label>
        <input id="zqps-link-id" class="form-control" type="number" min="1">
      </div>
      <button type="button" class="btn btn-default zqps-object" data-action="attach">
        {l s='Attach existing link (read-only)' d='Modules.Zipquantum.Admin'}
      </button>
    </section>

    <section class="panel zqps-panel">
      <h3>{l s='4. Bulk and queue' d='Modules.Zipquantum.Admin'}</h3>
      <div class="zqps-stats">
        {foreach from=$zq_queue_statuses item=status}
          <div><strong>{$zq_queue_stats[$status]|default:0|intval}</strong><span>{$status|escape:'htmlall':'UTF-8'}</span></div>
        {/foreach}
      </div>
      <div class="form-group">
        <label for="zqps-bulk-type">{l s='Bulk object type' d='Modules.Zipquantum.Admin'}</label>
        <select id="zqps-bulk-type" class="form-control">
          <option value="product">{l s='Products' d='Modules.Zipquantum.Admin'}</option>
          <option value="category">{l s='Categories' d='Modules.Zipquantum.Admin'}</option>
          <option value="promotion">{l s='Promotions and coupons' d='Modules.Zipquantum.Admin'}</option>
        </select>
      </div>
      <button type="button" class="btn btn-default zqps-action" data-action="bulkEnqueue">
        {l s='Add up to 500 objects to queue' d='Modules.Zipquantum.Admin'}
      </button>
      <button type="button" class="btn btn-primary zqps-action" data-action="processQueue">
        {l s='Process next batch' d='Modules.Zipquantum.Admin'}
      </button>
      <button type="button" class="btn btn-default zqps-action" data-action="retry">
        {l s='Retry failed' d='Modules.Zipquantum.Admin'}
      </button>
      <button type="button" class="btn btn-default zqps-action" data-action="resume">
        {l s='Resume blocked' d='Modules.Zipquantum.Admin'}
      </button>
      <details class="zqps-cron">
        <summary>{l s='Secured cron URL' d='Modules.Zipquantum.Admin'}</summary>
        <input class="form-control" type="text" readonly value="{$zq_cron_url|escape:'htmlall':'UTF-8'}">
      </details>
    </section>
  </div>

  <section class="panel zqps-panel">
    <div class="zqps-section-heading">
      <h3>{l s='Smart Links and simple analytics' d='Modules.Zipquantum.Admin'}</h3>
      <button type="button" class="btn btn-default zqps-action" data-action="refreshAnalytics">
        {l s='Refresh click totals' d='Modules.Zipquantum.Admin'}
      </button>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead><tr>
          <th>{l s='Object' d='Modules.Zipquantum.Admin'}</th>
          <th>{l s='Mode' d='Modules.Zipquantum.Admin'}</th>
          <th>{l s='Smart Link' d='Modules.Zipquantum.Admin'}</th>
          <th>{l s='Clicks' d='Modules.Zipquantum.Admin'}</th>
          <th>{l s='QR' d='Modules.Zipquantum.Admin'}</th>
          <th>{l s='Status' d='Admin.Global'}</th>
        </tr></thead>
        <tbody>
        {foreach from=$zq_associations item=assoc}
          <tr>
            <td>{$assoc.object_type|escape:'htmlall':'UTF-8'} #{$assoc.object_id|intval}</td>
            <td>{$assoc.management_mode|escape:'htmlall':'UTF-8'}</td>
            <td>
              {if !empty($assoc.smart_link.short_link)}
                <a href="{$assoc.smart_link.short_link|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener noreferrer">
                  {$assoc.smart_link.short_link|escape:'htmlall':'UTF-8'}
                </a>
              {else}-{/if}
            </td>
            <td>{$assoc.smart_link.clicks|default:0|intval}</td>
            <td>
              {if !empty($assoc.smart_link.qr)}
                <a download="zipquantum-qr.svg" href="{$assoc.smart_link.qr|escape:'htmlall':'UTF-8'}">
                  {l s='Download' d='Admin.Actions'}
                </a>
              {else}-{/if}
            </td>
            <td>{$assoc.local_status|escape:'htmlall':'UTF-8'}</td>
          </tr>
        {foreachelse}
          <tr><td colspan="6">{l s='No Smart Link association yet.' d='Modules.Zipquantum.Admin'}</td></tr>
        {/foreach}
        </tbody>
      </table>
    </div>
  </section>

  <div class="alert alert-info">
    {l s='Privacy: this module adds no storefront tracker, fingerprinting or advertising identifier. Deleting an object, disconnecting, or uninstalling never deletes a remote Smart Link.' d='Modules.Zipquantum.Admin'}
  </div>
</div>
