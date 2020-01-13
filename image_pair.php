<!DOCTYPE html>
<html>
<head>
	<title>Query/Match comparison</title>
	<script src="jquery-1.4.4.js" type="text/javascript"></script>
	<script src="php.default.min.js" type="text/javascript"></script>
	<script type="text/javascript" src="verovio-toolkit.js"></script>
	<script type="text/javascript" src="underscore-min.js"></script>

<!-- // CHANGEME!!! -->
<!-- Need to download local copy!! -->
	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.4/jquery-ui.min.js"></script>

<!-- // CHANGEME!!! -->
<!-- Need to download local copy!! -->
	<link rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.3/themes/base/jquery-ui.css" type="text/css"/>

	<script type="text/javascript">

	</script>
	<style type="text/css">
		#q_svg_output {
			z-index:1;
			position:absolute;
			opacity:5;
	/*		transform:  translate(5px, 24px); */
			order: 1;
		}
		#m_svg_output {
			z-index:1;
			position:absolute;
			opacity:5;
	/*		transform:  translate(-164px, 0px); */
			order: 2;
		}
		
		div {padding:0;}

		 .image_container {
		 	position: relative;
			left: 0px;
		 }
		.display_row {
		  display: flex;
		}
		.page_display {
		  flex: 50%;
		  padding: 5px;
		  flex-direction: row;
		}
		.page_image {
	/*   	border: 2px solid blue; */
		}
		.label {
			color: green;
		}
	
	</style>


<script>

	var	id_diat_mel_table = [];		

// Function to correct Verovio JSON bug:
	function resizeSVG(choice){
		if(choice=="query") var mySVG = $("#q_svg_output svg")[0];
		else var mySVG = $("#m_svg_output svg")[0];
		 var width = parseInt(mySVG.getAttributeNS(null, "width"), 10);
		 var height = parseInt(mySVG.getAttributeNS(null, "height"), 10);
//console.log(width+" x "+height)
//		 var targetWidth = $(window).width();
		 var targetWidth = document.getElementById("query_image").width;
		 if(targetWidth===width) return;
		 var aspectRatio = width/height;
//console.log("resize to: "+targetWidth+" x "+targetWidth/aspectRatio)
		 mySVG.setAttributeNS(null, "width", targetWidth+"px");
		 mySVG.setAttributeNS(null, "height", (targetWidth/aspectRatio)+"px");
	//	  document.getElementById('image').setAttributeNS(null, 'width', targetWidth+"px");
	}

//*************** Longest increasing subsequence
// Longest increasing subsequence code from https://rosettacode.org/wiki/Longest_increasing_subsequence#JavaScript
//	var _ = require('underscore');
	function findIndex(input){
		var len = input.length;
		var maxSeqEndingHere = _.range(len).map(function(){return 1;});
		for(var i=0; i<len; i++)
			for(var j=i-1;j>=0;j--)
				if(input[i] > input[j] && maxSeqEndingHere[j] >= maxSeqEndingHere[i])
					maxSeqEndingHere[i] = maxSeqEndingHere[j]+1;
		return maxSeqEndingHere;
	}
	function findSequence(input, result){
		var maxValue = Math.max.apply(null, result);
		var maxIndex = result.indexOf(Math.max.apply(Math, result));
		var output = [];
		output.push(input[maxIndex]);
		for(var i = maxIndex ; i >= 0; i--){
			if(maxValue==0)break;
			if(input[maxIndex] > input[i]  && result[i] == maxValue-1){
				output.push(input[i]);
				maxValue--;
			}
		}
		output.reverse();
		return output;
	}
	function lis(str) {
		return findSequence(str, findIndex(str));
	}
//***************

	var ngr_len = 5;
	function ngram_string(q_str, n) {
		if(!q_str.length) return false;
		queries = [];
		if(q_str.length<n) {
			array_push(queries,q_str + "%");
		}
		else if (q_str.length==n) {
			array_push(queries,q_str);
			}
			else {  
				for(i=0; i+n <= q_str.length; i++) {
					array_push(queries,substr(q_str,i,n));
				}
			}
		return queries;
	}
	var q_com_ng_loc = [];
	var m_com_ng_loc = [];
	function ngrams_in_common(q_str,m_str,n) {
		q_ngrams=ngram_string(q_str, n);

		for(i=0;i<=q_ngrams.length;i++) {
			var loc =m_str.indexOf(q_ngrams[i]);
			if(loc>=0) {
				q_com_ng_loc.push(i);
				m_com_ng_loc.push(loc);
			}
		}
	}

</script>


</head>

<body>

<?php
	if(!isset($_GET['colour'])) $colour = "blue";
	else $colour = $_GET['colour'];
	
// $qid is query ID; $mid is match ID
//	if(!isset($_GET['qid'])) $qid = "K3k19_007_1";
	if(!isset($_GET['qid'])) $qid = "GB-Lbl_K3k19_007_1";
	else $qid = $_GET['qid'];
//	if(!isset($_GET['mid'])) $mid = "A338d_003_0";
	if(!isset($_GET['mid'])) $mid = "GB-Lbl_A338d_003_0";
	else $mid = $_GET['mid'];

//	$qjpg_url = "http://doc.gold.ac.uk/~mas01tc/page_dir_50/".$qid.".jpg";
//	$mjpg_url = "http://doc.gold.ac.uk/~mas01tc/page_dir_50/".$mid.".jpg";
	$qjpg_url = "http://doc.gold.ac.uk/~mas01tc/new_page_dir_50/".$qid.".jpg";
	$mjpg_url = "http://doc.gold.ac.uk/~mas01tc/new_page_dir_50/".$mid.".jpg";
//	$qmei_url = "http://doc.gold.ac.uk/~mas01tc/EMO_search/mei_pages/".$qid.".mei";
//	$mmei_url = "http://doc.gold.ac.uk/~mas01tc/EMO_search/mei_pages/".$mid.".mei";
//	$qmei_url = "new_mei_pages/".$qid.".mei";
//	$mmei_url = "new_mei_pages/".$mid.".mei";
	$qmei_url = "http://doc.gold.ac.uk/~mas01tc/EMO_search/new_mei_pages/".$qid.".mei";
	$mmei_url = "http://doc.gold.ac.uk/~mas01tc/EMO_search/new_mei_pages/".$mid.".mei";
//	$grep_cmd = "grep ".$qid." ./id_diat_mel_lookup.txt";
	$codestrings_url = "data/latest_diat_mel_strs";
	$grep_cmd = "grep ".$qid." ".$codestrings_url;
	$q_diat_str = exec($grep_cmd);
//	$grep_cmd = "grep ".$mid." ./id_diat_mel_lookup.txt";
	$grep_cmd = "grep ".$mid." ".$codestrings_url;
	$m_diat_str = exec($grep_cmd);
	
?>

<div id="qmei" style="display: none;">
    <?php 
        $mei = file_get_contents($qmei_url);
        echo htmlspecialchars($mei); /* You have to escape because the result will not be valid HTML otherwise. */
   ?>
</div>
<div id="mmei" style="display: none;">
    <?php 
        $mei = file_get_contents($mmei_url);
        echo htmlspecialchars($mei); /* You have to escape because the result will not be valid HTML otherwise. */
    ?>
</div>



<div class="display_row">
	<div left="0px" width="1050px" id="query_container" class="page_display">
		<div id="query_page_display">Query:	<span id="query_label" class="label"><?php echo $qid; ?> </span></div>
		<div><div  id="q_svg_output"></div>
		<img id="query_image" class="page_image" width="500" src="<?php echo $qjpg_url; ?>"></div>
	</div>
	<div left="0px" width="1050px" id="match_container" class="page_display">	
		<div id="match_page_display">Match:	<span id="query_label" class="label"><?php echo $mid; ?> </span></div>
		<div><div  id="m_svg_output"></div>
		<img id="match_image" class="page_image" width="500" src="<?php echo $mjpg_url; ?>"></div>
	</div>
</div>



<script>

// Verovio code:	
	///////////////////////////
	/* Create the vrvToolkit */
	///////////////////////////
	var vrvToolkit = new verovio.toolkit();
	var zoom = 100;
	var pageHeight = 1485;
	var pageWidth = 1050;
var q_options;
	function set_query_Options() {
//    console.log("Setting query options for Verovio");
	    pageHeight = document.getElementById("query_image").height * 100 / zoom ;
//    console.log("page height: "+pageHeight);
	    pageWidth = document.getElementById("query_image").width * 100 / zoom ;
//    console.log("page width: "+pageWidth);
	    options = {
			pageHeight: pageHeight,
			pageWidth: pageWidth,
			scale: zoom,
			noLayout: 1
		    };
	    vrvToolkit.setOptions(options);
	}
var m_options;
	function set_match_Options() {
//    console.log("Setting match options for Verovio");
	    pageHeight = document.getElementById("match_image").height * 100 / zoom ;
//    console.log("page height: "+pageHeight);
	    pageWidth = document.getElementById("match_image").width * 100 / zoom ;
//    console.log("page width: "+pageWidth);
	    options = {
			pageHeight: pageHeight,
			pageWidth: pageWidth,
			scale: zoom,
			noLayout: 1
		    };
	    vrvToolkit.setOptions(options);
	}
	var overlay_colour = "<?php echo $colour; ?>";

	qid="<?php echo $qid; ?>";
	mid="<?php echo $mid; ?>";
	console.log("qid: "+qid);	
	console.log("mid: "+mid);	

	q_mel_str='<?php echo $q_diat_str;?>'.split(" ")[1];
	m_mel_str='<?php echo $m_diat_str;?>'.split(" ")[1];
	ngrams_in_common(q_mel_str,m_mel_str,ngr_len);
/*
	var q_lis = lis(q_com_ng_loc)
	var m_lis = lis(m_com_ng_loc)
*/
	var q_lis = q_com_ng_loc
	var m_lis = m_com_ng_loc
console.log("query locs: "+q_lis)
console.log("query lis: "+lis(q_lis))
console.log("match locs: "+m_lis)
console.log("match lis: "+lis(m_lis))

	var qmei_txt = htmlspecialchars_decode(document.getElementById("qmei").innerHTML);
	var mmei_txt = htmlspecialchars_decode(document.getElementById("mmei").innerHTML);
    
	set_query_Options();
	var q_v_svg = vrvToolkit.renderData(qmei_txt);
	$("#q_svg_output").html(q_v_svg);
	resizeSVG("query");

var q_n = 0;
$("#q_svg_output g.note").each (function (i){
	if (q_lis.indexOf(i)>=0) {
		q_n = ngr_len;
	}
	if(q_n>=0) {
//	    $(this).attr("fill", "red").attr("stroke", "red");
	    $(this).attr("fill", "HotPink").attr("stroke", "HotPink");
	    q_n--;
    }
    else {
//	    $(this).attr("fill", "blue").attr("stroke", overlay_colour);
	    $(this).attr("fill", "Teal").attr("stroke", overlay_colour);
    }
});

	set_match_Options();
	var m_v_svg = vrvToolkit.renderData(mmei_txt);
	$("#m_svg_output").html(m_v_svg);
	resizeSVG("match");

var m_n = 0;
$("#m_svg_output g.note").each (function (j){
	if (m_lis.indexOf(j)>=0) {
		m_n = ngr_len;
	}
	if(m_n>=0) {
//	    $(this).attr("fill", "red").attr("stroke", "red");
	    $(this).attr("fill", "HotPink").attr("stroke", "HotPink");
	    m_n--;
    }
    else {
//	    $(this).attr("fill", "blue").attr("stroke", overlay_colour);
	    $(this).attr("fill", "Teal").attr("stroke", overlay_colour);
    }
});
//window.opener.document.focus();

</script>

</body>
</html>
