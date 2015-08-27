<?php
// Scan this directory and add any files with 'Template.html' in the file name to
// to a templates array. The files will be serialized before being added to the array.
$htmlFiles = glob("viewer-assets/templates/*.[hH][tT][mM][lL]");
$templateFiles = preg_grep('/\w*(template\.html)$/i', $htmlFiles);

foreach ($templateFiles as $filepath) {
    $filename = basename($filepath);
    $tplName = str_ireplace('template.html', '', $filename);
    $tplFile = preg_replace("/\s+/", " ", file_get_contents($filepath));
    $tpls[$tplName] = $tplFile;
}
?>
<!DOCTYPE html>
<html lang="en">
<head id="Head1">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>PCC HTML5 PHP Sample</title>
    <link rel="icon" href="viewer-assets/img/favicon.ico" type="image/x-icon"/>

    <link rel="stylesheet" href="viewer-assets/css/normalize.min.css"/>
    <link rel="stylesheet" href="viewer-assets/css/viewercontrol.css"/>
    <link rel="stylesheet" href="viewer-assets/css/viewer.css"/>

    <script type="text/javascript">
        var PCCViewer = window.PCCViewer || {};
    </script>

    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script>window.jQuery || document.write('<script src="viewer-assets/js/jquery-1.10.2.min.js"><\/script>');</script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/underscore.js/1.6.0/underscore-min.js"></script>
    <script>window._ || document.write('<script src="viewer-assets/js/underscore.min.js"><\/script>');</script>
    <script src="viewer-assets/js/jquery.hotkeys.min.js"></script>

    <!--[if lt IE 9]>
    <link rel="stylesheet" href="viewer-assets/css/legacy.css">
    <script src="viewer-assets/js/html5shiv.js"></script>
    <![endif]-->
    
    <script src="//pcc-assets.accusoft.com/v10.3/js/viewercontrol.js"></script>
    <script src="//pcc-assets.accusoft.com/v10.3/js/viewer.js"></script>

    <!-- Configuration information used for this sample. -->
    <script src="sample-config.js"></script>
</head>
<body>

<div id="viewer1"></div>

<div id="attachments" style="display:none;">
    <b>Attachments:</b>
    <p id="attachmentList">
    </p>
</div>

<script>
    var viewer;
    jQuery(function($) {
        var viewingSessionId = '';
        var query = (function parseQuery() {
            var query = {};
            var temp = window.location.search.substring(1).split('&');
            for (var i = temp.length; i--;) {
                var q = temp[i].split('=');
                query[q.shift()] = decodeURI(q.join('='));
            }
            return query;
        })();

        // setup -- you probably already have this, so you can ignore it
        function createSession(data) {
            return $.ajax({
                url: sampleConfig.imageHandlerUrl + '/CreateSession',
                data: data
            }).then(function (response) {
                viewingSessionId = response.viewingSessionId || response.documentID;

                return viewingSessionId;
            });
        }

        function getTemplate(templateName) {
            return $.ajax({url: templateName})
                .then(function (response) {
                    return response;
                });
        }

        function getJson(fileName) {
            return $.ajax({ url: fileName })
                .then(function (response) {
                    // IIS Express will not use the correct MIME type for json, so we may need to parse it as a string
                    if (typeof response === 'string') {
                        return JSON.parse(response);
                    }

                    return response;
                });
        }

        function getResourcesAndEmbedViewer(demoConfig) {
            var sessionData = {};

            if (query.viewingSessionId) {
                sessionData.viewingSessionId = query.viewingSessionId;
            } else {
                sessionData.document = query.document || 'PdfDemoSample.pdf';
            }

            $.when(
                sessionData.document, // args[0]
                createSession(sessionData), // args[1]
                <?php echo json_encode($tpls) ?>, // args[2]
                getJson(sampleConfig.viewerAssetsPath + '/languages/' + sampleConfig.languageFile), // args[3]
                getJson('redactionReason.json'), // args[4]
                getJson('predefinedSearch.json'), // args[5]
                demoConfig.options || {}) // args[6]
                .done(buildViewerOptions);
        }

        function buildViewerOptions() {
            var args = [].slice.call(arguments);

            var optionsOverride = args.pop(); // always last arg

            var options = {
                documentID: encodeURIComponent(args[1]),
                language: args[3],
                predefinedSearch: args[5],
                template: args[2],
                signatureCategories: 'Signature,Initials,Title',
                immediateActionMenuMode: 'hover',
                redactionReasons: args[4],
                documentDisplayName: args[0],
                uiElements: {
                    download: true,
                    fullScreenOnInit: true,
                    advancedSearch: true
                }
            };

            var combinedOptions = _.extend(optionsOverride, options);

            embedViewer(combinedOptions);
        }

        function embedViewer(options) {
            viewer = $('#viewer1').pccViewer(options);

            // The following javascript will process any attachments for the
            // email message document types (.EML and .MSG).
            setTimeout(requestAttachments, 500);

            var countOfAttachmentsRequests = 0;

            function receiveAttachments(data, textStatus, jqXHR) {

                if (data == null || data.status != 'complete') {
                    // The request is not complete yet, try again after a short delay.
                    setTimeout(requestAttachments, countOfAttachmentsRequests * 1000);
                }

                if (data.attachments.length > 0) {
                    var links = '';
                    for (var i = 0; i < data.attachments.length; i++) {
                        var attachment = data.attachments[i];
                        links += '<a href="?viewingSessionId=' + attachment.viewingSessionId + '" target="blank">' + attachment.displayName + '</a><br/>';
                    }

                    $('#attachmentList').html(links);
                    $('#attachments').show();
                }
            }

            function requestAttachments() {
                if (countOfAttachmentsRequests < 10) {
                    countOfAttachmentsRequests++;
                    $.ajax(sampleConfig.imageHandlerUrl + '/ViewingSession/u' + viewingSessionId + '/Attachments', {dataType: 'json'}).done(receiveAttachments).fail(requestAttachments);
                }
            }

            // Custom code to insert annotations into DB
            $(".pcc-js-postToDB").on("click", function(){
                var allMarks = viewer.viewerControl.getAllMarks();
				var userName = "FooBar";

                // Add a username to each mark
                var i;
                for (i=0; i < allMarks.length; ++i){
                    allMarks[i].setData("user" , userName);
                }
				
				function postData(data){
					$.ajax({
						method: "POST",
						contentType: "application/json",
						url: "viewer-webtier/dbDemo.php?DocumentID=u" + options.documentID +"&username=" + userName,
						data: data
					}).done(function(){
						console.log(arguments);
					});
				}

                if (allMarks.length > 0){
					//If there is at least one mark on the page we need to serialize and post to the server
					viewer.viewerControl.serializeMarks(allMarks).then(
						function success (markObjects){
							var markStr = JSON.stringify(markObjects);
							postData(markStr);
							console.log(markStr);
						});
				}else{
					//No annotations on the page, no need to serialize. Post an empty array.
					var markStr = JSON.stringify(allMarks);
					postData(markStr);
				}
            });
            
            $(".pcc-js-loadFromDB").on("click", function(){

                //In a real setting, we would pass a param to the server to fetch a certain annotation group, or specific annotation.
                // For thise demonstration, we've hardcoded an annotation ID to pull out of the database
                $.get('viewer-webtier/dbDemo.php',{'username':"FooBar", 'documentID': "u" + options.documentID}, function (data){
                    viewer.viewerControl.deleteAllMarks();
                    viewer.viewerControl.deserializeMarks(data);
                    console.log(data);
                });
            });
        }

        getResourcesAndEmbedViewer({
            options: {
                imageHandlerUrl: sampleConfig.imageHandlerUrl,
                resourcePath: sampleConfig.viewerAssetsPath + '/img'
            }
        });
    });
</script>
</body>
</html>
