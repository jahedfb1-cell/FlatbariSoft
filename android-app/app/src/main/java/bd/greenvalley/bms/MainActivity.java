package bd.greenvalley.bms;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.ActivityNotFoundException;
import android.content.ClipData;
import android.content.ContentResolver;
import android.content.ContentValues;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.net.Uri;
import android.os.Bundle;
import android.os.Environment;
import android.provider.MediaStore;
import android.text.InputType;
import android.util.Base64;
import android.view.Menu;
import android.view.MenuItem;
import android.webkit.JavascriptInterface;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceError;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.EditText;
import android.widget.Toast;

import java.io.IOException;
import java.io.OutputStream;

/**
 * Green Valley BMS - a thin WebView shell around the web app.
 *
 * What a bare WebView does NOT do, and this class adds:
 *  - the server address is configurable (asked once, remembered; a live build can bake one in);
 *  - the invoice-photo <input type=file> works (camera + gallery);
 *  - PDF / Excel "downloads" work: the web app builds them as blob: URLs, which a WebView cannot
 *    save by itself, so the page hands the bytes to {@link Bridge} (window.GVApp) which writes them
 *    to Downloads and opens them;
 *  - navigation is pinned to the server's own host, so the JavaScript bridge is only ever exposed
 *    to the real site.
 */
public class MainActivity extends Activity {

    private static final String PREFS = "gv_bms";
    private static final String KEY_SERVER_URL = "server_url";
    private static final int REQ_FILE = 1001;

    private WebView web;
    private ValueCallback<Uri[]> fileCallback;
    private Uri cameraUri;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setTitle(getString(R.string.app_name));

        web = new WebView(this);
        setContentView(web);
        configureWebView();

        if (serverUrl().isEmpty()) {
            promptForServer(true);
        } else {
            web.loadUrl(serverUrl());
        }
    }

    // ---------------------------------------------------------------- WebView

    private void configureWebView() {
        WebSettings s = web.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);          // the web app keeps its login token in localStorage
        s.setAllowFileAccess(false);
        s.setAllowContentAccess(false);
        s.setMediaPlaybackRequiresUserGesture(true);
        s.setUserAgentString(s.getUserAgentString() + " GVBMSApp/" + BuildConfig.VERSION_NAME);

        web.addJavascriptInterface(new Bridge(), "GVApp");

        web.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                return handleNavigation(request.getUrl());
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                if (request.isForMainFrame()) {
                    view.loadDataWithBaseURL(null, errorPage(), "text/html", "utf-8", null);
                }
            }
        });

        web.setWebChromeClient(new WebChromeClient() {
            @Override
            public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback, FileChooserParams params) {
                return openFileChooser(callback, params);
            }
        });
    }

    /** true = we handled it (stay out of the WebView); false = let the WebView load it. */
    private boolean handleNavigation(Uri uri) {
        String scheme = uri.getScheme() == null ? "" : uri.getScheme();

        if (scheme.equals("gvapp")) {                       // buttons on our own error page
            if ("change".equals(uri.getHost())) promptForServer(false);
            else web.loadUrl(serverUrl());
            return true;
        }
        if (scheme.equals("blob") || scheme.equals("about") || scheme.equals("data")) {
            return false;
        }
        String serverHost = Uri.parse(serverUrl()).getHost();
        if (serverHost != null && serverHost.equalsIgnoreCase(uri.getHost())) {
            return false;                                    // the app's own site
        }
        try {                                                // anything else opens outside the app
            startActivity(new Intent(Intent.ACTION_VIEW, uri));
        } catch (ActivityNotFoundException ignored) {
            // nothing can open it - drop it
        }
        return true;
    }

    private String errorPage() {
        return "<html><head><meta name='viewport' content='width=device-width,initial-scale=1'>"
            + "<style>body{font-family:sans-serif;padding:28px;color:#16324f;text-align:center}"
            + "a{display:block;margin:14px auto;padding:14px;max-width:320px;border-radius:10px;"
            + "background:#0F766E;color:#fff;text-decoration:none}a.alt{background:#e5eef0;color:#16324f}</style></head><body>"
            + "<h2>সার্ভারে সংযোগ হয়নি</h2>"
            + "<p>ইন্টারনেট সংযোগ বা সার্ভারের ঠিকানা দেখুন।</p>"
            + "<p style='font-size:13px;color:#667'>" + escape(serverUrl()) + "</p>"
            + "<a href='gvapp://retry'>আবার চেষ্টা করুন</a>"
            + "<a class='alt' href='gvapp://change'>সার্ভার বদলান</a></body></html>";
    }

    private static String escape(String s) {
        return s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;");
    }

    @Override
    public void onBackPressed() {
        if (web.canGoBack()) web.goBack();
        else super.onBackPressed();
    }

    // ------------------------------------------------------------ server address

    private String serverUrl() {
        SharedPreferences p = getSharedPreferences(PREFS, Context.MODE_PRIVATE);
        return p.getString(KEY_SERVER_URL, BuildConfig.DEFAULT_SERVER_URL);
    }

    /** Returns a clean base URL, or null if it isn't a usable http(s) address. */
    private String normalize(String raw) {
        String s = raw == null ? "" : raw.trim();
        if (s.isEmpty()) return null;
        if (!s.contains("://")) s = "https://" + s;
        Uri u = Uri.parse(s);
        String scheme = u.getScheme();
        if (u.getHost() == null || u.getHost().isEmpty()) return null;
        if (!"http".equals(scheme) && !"https".equals(scheme)) return null;
        while (s.endsWith("/")) s = s.substring(0, s.length() - 1);
        return s;
    }

    private void promptForServer(final boolean firstRun) {
        final EditText input = new EditText(this);
        input.setInputType(InputType.TYPE_TEXT_VARIATION_URI);
        input.setHint("https://example.com");
        input.setText(serverUrl());
        input.setSelection(input.getText().length());

        AlertDialog.Builder b = new AlertDialog.Builder(this)
            .setTitle(R.string.server_title)
            .setMessage(R.string.server_message)
            .setView(input)
            .setCancelable(!firstRun)
            .setPositiveButton(R.string.server_open, (d, w) -> {
                String url = normalize(input.getText().toString());
                if (url == null) {
                    Toast.makeText(this, R.string.server_invalid, Toast.LENGTH_LONG).show();
                    promptForServer(firstRun);
                } else if (url.startsWith("http://") && !BuildConfig.ALLOW_HTTP) {
                    Toast.makeText(this, R.string.server_http_blocked, Toast.LENGTH_LONG).show();
                    promptForServer(firstRun);
                } else {
                    getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putString(KEY_SERVER_URL, url).apply();
                    web.loadUrl(url);
                }
            });
        if (!firstRun) b.setNegativeButton(R.string.server_cancel, null);
        b.show();
    }

    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        menu.add(0, 1, 0, R.string.menu_reload);
        menu.add(0, 2, 1, R.string.menu_change_server);
        return true;
    }

    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        if (item.getItemId() == 1) web.reload();
        else if (item.getItemId() == 2) promptForServer(false);
        return true;
    }

    // ------------------------------------------------------------ file chooser

    private boolean openFileChooser(ValueCallback<Uri[]> callback, WebChromeClient.FileChooserParams params) {
        if (fileCallback != null) fileCallback.onReceiveValue(null);
        fileCallback = callback;
        cameraUri = null;

        Intent pick = params.createIntent();               // gallery / file picker, honours accept=""
        Intent chooser = Intent.createChooser(pick, getString(R.string.choose_file));

        boolean wantsImage = false;
        for (String type : params.getAcceptTypes()) {
            if (type != null && (type.startsWith("image") || type.isEmpty())) wantsImage = true;
        }
        if (params.getAcceptTypes().length == 0) wantsImage = true;
        if (wantsImage) {
            ContentValues v = new ContentValues();
            v.put(MediaStore.Images.Media.DISPLAY_NAME, "invoice_" + System.currentTimeMillis() + ".jpg");
            v.put(MediaStore.Images.Media.MIME_TYPE, "image/jpeg");
            cameraUri = getContentResolver().insert(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, v);
            if (cameraUri != null) {
                Intent camera = new Intent(MediaStore.ACTION_IMAGE_CAPTURE).putExtra(MediaStore.EXTRA_OUTPUT, cameraUri);
                chooser.putExtra(Intent.EXTRA_INITIAL_INTENTS, new Intent[]{camera});
            }
        }
        try {
            startActivityForResult(chooser, REQ_FILE);
        } catch (ActivityNotFoundException e) {
            fileCallback = null;
            return false;
        }
        return true;
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode != REQ_FILE || fileCallback == null) return;

        Uri[] results = null;
        if (resultCode == RESULT_OK) {
            if (data != null && data.getClipData() != null) {
                ClipData clip = data.getClipData();
                results = new Uri[clip.getItemCount()];
                for (int i = 0; i < results.length; i++) results[i] = clip.getItemAt(i).getUri();
            } else if (data != null && data.getData() != null) {
                results = new Uri[]{data.getData()};
            } else if (cameraUri != null) {
                results = new Uri[]{cameraUri};            // taken with the camera
            }
        }
        // The empty MediaStore row created for the camera is useless unless a photo was taken with it.
        boolean cameraUsed = results != null && cameraUri != null && results.length == 1 && cameraUri.equals(results[0]);
        if (cameraUri != null && !cameraUsed) getContentResolver().delete(cameraUri, null, null);

        fileCallback.onReceiveValue(results);
        fileCallback = null;
        cameraUri = null;
    }

    // ------------------------------------------------------- downloads (window.GVApp)

    /** Called from the web page's JavaScript. Methods run on a background thread. */
    private class Bridge {
        private OutputStream out;
        private Uri outUri;
        private String outMime;

        @JavascriptInterface
        public String version() {
            return BuildConfig.VERSION_NAME;
        }

        /** Start a file: the page then sends base64 chunks and calls endFile(). */
        @JavascriptInterface
        public synchronized boolean beginFile(String name, String mime) {
            closeQuietly();
            try {
                ContentValues v = new ContentValues();
                v.put(MediaStore.Downloads.DISPLAY_NAME, name.replaceAll("[^A-Za-z0-9._-]", "_"));
                v.put(MediaStore.Downloads.MIME_TYPE, mime);
                v.put(MediaStore.Downloads.RELATIVE_PATH, Environment.DIRECTORY_DOWNLOADS + "/GreenValley");
                v.put(MediaStore.Downloads.IS_PENDING, 1);
                ContentResolver cr = getContentResolver();
                outUri = cr.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, v);
                if (outUri == null) return false;
                out = cr.openOutputStream(outUri);
                outMime = mime;
                return out != null;
            } catch (Exception e) {
                closeQuietly();
                return false;
            }
        }

        @JavascriptInterface
        public synchronized boolean appendChunk(String base64) {
            if (out == null) return false;
            try {
                out.write(Base64.decode(base64, Base64.DEFAULT));
                return true;
            } catch (IOException | IllegalArgumentException e) {
                closeQuietly();
                return false;
            }
        }

        @JavascriptInterface
        public synchronized boolean endFile() {
            if (out == null || outUri == null) return false;
            final Uri uri = outUri;
            final String mime = outMime;
            try {
                out.close();
                out = null;
                ContentValues done = new ContentValues();
                done.put(MediaStore.Downloads.IS_PENDING, 0);
                getContentResolver().update(uri, done, null, null);
            } catch (IOException e) {
                closeQuietly();
                return false;
            }
            runOnUiThread(() -> {
                Toast.makeText(MainActivity.this, R.string.saved_to_downloads, Toast.LENGTH_SHORT).show();
                Intent view = new Intent(Intent.ACTION_VIEW).setDataAndType(uri, mime)
                    .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION | Intent.FLAG_ACTIVITY_NEW_TASK);
                try {
                    startActivity(view);
                } catch (ActivityNotFoundException e) {
                    Toast.makeText(MainActivity.this, R.string.no_viewer, Toast.LENGTH_LONG).show();
                }
            });
            outUri = null;
            return true;
        }

        private void closeQuietly() {
            try {
                if (out != null) out.close();
            } catch (IOException ignored) {
                // nothing to do
            }
            out = null;
            if (outUri != null) {
                getContentResolver().delete(outUri, null, null);   // drop the half-written file
                outUri = null;
            }
        }
    }
}
