@@
-                if (!(window as any).FB) {
-            (window as any).fbAsyncInit = function () {
-                (window as any).FB.init({
-                    appId: whatsapp_app_id,
-                    cookie: true,
-                    xfbml: true,
-                    version: 'v20.0',
-                });
-            };
-        }
+        if (!(window as any).FB) {
+            (window as any).fbAsyncInit = function () {
+                (window as any).FB.init({
+                    appId: whatsapp_app_id,
+                    cookie: true,
+                    xfbml: true,
+                    version: 'v21.0',
+                });
+            };
+        }
@@
-            {
-                config_id: whatsapp_config_id,
-                response_type: 'code',
-                override_default_response_type: true,
-                extras: {
-                    feature: 'whatsapp_embedded_signup',
-                    featureType: 'only_waba_sharing',
-                    version: 2,
-                },
-            },
+            {
+                config_id: whatsapp_config_id ?? '1061983729651940',
+                response_type: 'code',
+                override_default_response_type: true,
+                extras: {
+                    version: 'v4',
+                    sessionInfoVersion: 3,
+                    featureType: 'whatsapp_business_app_onboarding',
+                },
+            },
