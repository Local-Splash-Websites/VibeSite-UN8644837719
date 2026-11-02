# oddjobprosllc.com: static copy

An exact copy of the Duda site https://www.oddjobprosllc.com (Duda site ID `bc06e187`), served without Duda.

- `site/` is the web root. Each page is `site/<page>.html` (`index.html` for `/`), the HTML Duda
  served, unchanged except that asset URLs point at local copies.
- `site/_d/<host>/...` holds every file the pages load from Duda's CDNs (`irp`/`static`/`vid.cdn-website.com`,
  `*.multiscreensite.com`, `static-cdn.dwhitelabel.com`) and from cdnjs/jsDelivr, at the same path as on the CDN.
- `site/editor/apps/modules/runtime/` holds Duda's runtime script chunks, which the runtime loads from that fixed path.
- Google Maps, booking widgets and Duda's page-view pixel still load from their own hosts.

## Forms

The copied Duda forms post JSON to `/_dm/s/rt/actions/form/<id>`. `.htaccess` sends that to
`site/lead-webhook.php`, which maps the field ids to labels with `form-fields.json`, forwards the
lead to `LEADS_API_URL` (see `.env.example`) and answers in Duda's format, so the form goes on to its
thank-you page as before. Without `LEADS_API_URL` the form shows its error message and nothing is sent.

## Run

    docker build -t oddjobprosllc . && docker run -p 8080:80 -e LEADS_SITE_NAME=bc06e187 oddjobprosllc
