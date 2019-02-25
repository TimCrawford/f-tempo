<?php
	 header('Access-Control-Allow-Origin: http://doc.gold.ac.uk', false);
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EMO Search</title>

    <script src='jquery.min.js'></script>
    <script src='jquery.zoom.js'></script>
    <link rel="stylesheet" type="text/css" href="styles.css">


    <script>

      // Global variables
            var num_res_disp = 15; // default
            var last_res_disp = 0; // last result to be displayed  NB FIXME - doesn't always work!!
            var query_id = "";
            var last_query_id = ""; // the last query, to return to NB FIXME (should be an array of queries)
            var db_name ="";
            var highlighted_result_row = 0;
            var jaccard = true;

            //Arrays for displaying match stats in result list
            var matched_words = [];
            var words_in_page = [];

        // Array of page IDs loaded from database on initialisatiom
            var lines = [];

        //    UID for identifying user to logs, etc.
            var UID = "";
            var can_store_UID = false;
            if (storageAvailable('localStorage')) {
              console.log("Local Storage available!")
              if(!localStorage.getItem("userID")) {
                  console.log("Setting new UID!")
                  localStorage.setItem("userID",uniqueID());
                  UID = localStorage.getItem("userID");
              }
              else UID = localStorage.getItem("userID");
              can_store_UID = true;
            }
            else {
                  console.log("No Local Storage available! Setting new unique ID")
                  UID = uniqueID();
            }
            console.log("UID is "+UID);
/*
        // Gets ngram length from user for opening arbitrary database
            function get_ng_len() {
                var txt;
                var ng_len = prompt("Enter ngram length:", "6");
                if($.isNumeric(ng_len)) {
                    if ((ng_len != null) && (ng_len != "")) {
                        txt = "You asked for ngrams " + ng_len + " long!";
                        if((parseInt(ng_len)>2)&&(parseInt(ng_len)<16)) {
                          document.getElementById("messages").innerHTML = txt;
                          return ng_len;
                        }
                        else alert("Too short!");
                    }
                }
                else alert("Not a number!");
                return false;
            }
*/

            function load_page_query(id) {
                image= id + ".jpg";
                document.getElementById("q_page_display").innerHTML = "  Current page: "+image;
                document.getElementById("img_display").innerHTML = "<img id='query_image' src='http://doc.gold.ac.uk/~mas01tc/page_dir/"+image+"' role=\"presentation\"/>";
                $('#img_display').zoom();
//                load_lyrics(id, true);
                show_display_panel();
                show_query_display();
            }
            function get_query_from_id(id) {
                for(var i=0;i<lines.length;i++) {
                    if((lines[i].startsWith(">"+id))||(lines[i].startsWith(id))) return lines[i];
                }
                return false
            }

            // Basic remote search function.
            function search(id,jaccard,num_res_disp) {
//                fetch('http://localhost:3000/?id='+id+"&jaccard="+jaccard+"&num_res="+num_res_disp)
//                fetch('http://myserver.doc.gold.ac.uk:58265/?id='+id+"&jaccard="+jaccard+"&num_res="+num_res_disp)
                fetch('http://www.doc.gold.ac.uk/usr/265/?id='+id+"&jaccard="+jaccard+"&num_res="+num_res_disp)
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(myJson) {
                        show_results(myJson);
                    });
            }

            function do_search(id,jaccard,num_res_disp) {
                    var t0 = performance.now();
                    search(id,jaccard,num_res_disp);
                    var t1 = performance.now();
                    var report_string = 'Search '+id+' took ' + (t1 - t0).toFixed(4) + ' ms ';
                    report_string += document.getElementById('rank_toggle').innerText;
                    console.log(report_string);
            }

            function preloadImages(srcs) {
                if (!preloadImages.cache) {
                   preloadImages.cache = [];
                }
                var img;
                // leave out srcs[0] as it's the query, and already loaded
                for (var i = 1; i < srcs.length; i++) {
                   img = new Image();
                   img.src = srcs[i];
                   preloadImages.cache.push(img);
                }
            }
            var imageSrcs = [];

            function show_results(json) {
                load_page_query(query_id);
                var result_num = 0;
                var scores_pruned = json;
                show_result_table();
                var table_html = "";
                table_html = "<thead><tr><th colspan='2'><small>"+num_res_disp+" best</small></th> <th><small>Relation to query</small></th> </tr></thead>";
                table_html += "<tbody class='table_body'>";
                last_res_disp = 0;
                for(q=0;q<=num_res_disp;q++) if(q<scores_pruned.length) {
                // for Jaccard distance
                    if(jaccard) var rank_factor = 1 - scores_pruned[q].jaccard;
                // for basic search
                    else var rank_factor = scores_pruned[q].num / scores_pruned[0].num_words;
                    matched_words[q] = scores_pruned[q].num;
                    words_in_page[q] = scores_pruned[q].num_words;
                    var result_row_id = "result_row"+q;
                    var target_id = scores_pruned[q].id;
                    var sim_choice_id = "sim_choice"+q;
                    var sim_id = "sim"+q;
                    imageSrcs.push("http://doc.gold.ac.uk/~mas01tc/page_dir/"+scores_pruned[q].id+".jpg");
                    if(scores_pruned[q].id == query_id)   {
                        document.getElementById("messages").innerHTML = words_in_page[q] + " words in query";
                        table_html +=
                            "<tr class='id_list_name' id='"+result_row_id
                            +"' onclick='load_result_image(\""+target_id+"\","+q+","+(rank_factor*100).toFixed(1)+");'>"
                                +"<td text-align='center' style='color:blue'><small>" +target_id+"</small></td>"
                                +"<td style='border-left:"+(rank_factor*100).toFixed(2)+"px solid red'  ></td>"
                                +"<td width='160px' id='"+sim_choice_id+"'>"
                                    +"<select class='drop_downs' "
                                        +"onchange='log_user_choice(\""+query_id+"\",\""
                                            +target_id+"\","
                                            +q+", \""
                                            +db_name+"\");'"
                                        +" id='"+sim_id+"'>"
                                            +"<option selected' value='0'></option>"
                                            +"<option value='notm'>Not music!</option>"
                                    +"</select>"
                                +"</td>"+"</tr>";
                    }
                    else {
                        table_html +=
                            "<tr class='id_list_name' id='"+result_row_id
                            +"' onclick='load_result_image(\""+target_id+"\","+q+","+(rank_factor*100).toFixed(1)+");'>"
                                +"<td text-align='center' style='color:blue'><small>" +target_id+"</small></td>"
                                +"<td style='border-left:"+(rank_factor*100).toFixed(2)+"px solid red' ></td>"
                                + "<td id='"+sim_choice_id+"'>"
                                    +"<select  class='drop_downs'"
                                        +"onchange='log_user_choice(\""+query_id+"\",\""
                                            +target_id+"\","
                                            +q+", "
                                            +"\""+db_name+"\");'"
                                        +" id='"+sim_id+"'>"
                                            +"<option selected' value='0'></option>"
                                            +"<option value='dupl'>Duplicate page</option>"
                                            +"<option value='same'>Same music</option>"
                                            +"<option value='relv'>Related music</option>"
                                            +"<option value='notm'>Not music!</option>"
                                    +"</select>"
                                +"</td>"+"</tr>";
                        last_res_disp++;
                        }
                }
                preloadImages(imageSrcs);
                table_html += "</tbody>";
                document.getElementById('result_table').innerHTML = table_html;
                load_result_image(query_id, 0, 100);
                show_result_image();
            }

        // UI functions
            function show_database_panel() {
                document.getElementById("database_panel").style.visibility = "visible";
            }
            function hide_database_panel() {
                document.getElementById("database_panel").style.visibility = "hidden";
            }
            function show_query_panel() {
                document.getElementById("query_panel").style.visibility = "visible";
            }
            function hide_query_panel() {
                document.getElementById("query_panel").style.visibility = "hidden";
            }
            function show_display_panel() {
                document.getElementById("display_panel").style.visibility = "visible";
            }
            function hide_display_panel() {
                document.getElementById("display_panel").style.visibility = "hidden";
            }
            function show_query_display() {
                document.getElementById("query_display").style.visibility = "visible";
            }
            function hide_query_display() {
                document.getElementById("query_display").style.visibility = "hidden";
            }
            function show_result_table() {
                document.getElementById("res_table_label").innerHTML = "Results";
                document.getElementById("res_table_div").innerHTML = '<table id="result_table" text-align="center" width="200" border="1px"></table>';
                document.getElementById("res_table_label").style.visibility = "visible";
                document.getElementById("res_table_div").style.visibility = "visible";
            }
            function hide_result_table() {
                document.getElementById("res_table_label").style.visibility = "hidden";
                document.getElementById("res_table_div").style.visibility = "hidden";
            }
            function highlight_result_row(rank) {
                var rowID = "";
                for(var i=0;i<=last_res_disp;i++) {
                    rowID = "result_row"+i;
                    document.getElementById(rowID).style.backgroundColor = "White";
                }
                rowID = "result_row"+rank;
                document.getElementById(rowID).style.backgroundColor = "LightPink";
                highlighted_result_row = rank;
            }
            function load_result_image(id, rank, percent) {
                if(!id) {
                    document.getElementById("result_id_msg").innerHTML = "";
                    document.getElementById("result_img_display").innerHTML = "";
                    return false;
                }
                image= id + ".jpg";
                if(query_id != id) document.getElementById("result_id_msg").innerHTML = "Rank: "+rank+" ("+percent+"%) "+id+" ("+matched_words[rank]+"/"+words_in_page[rank]+" words in page match the query)";
                else document.getElementById("result_id_msg").innerHTML = "Query: "+id;
                document.getElementById("result_img_display").innerHTML = "<img width = '400px' src='http://doc.gold.ac.uk/~mas01tc/page_dir/"+image+"' />";
                highlight_result_row(rank);
                $('#result_img_display').zoom();
                document.getElementById("idText").value = id;
//                load_lyrics(id, false);
            }
            function hide_result_image() {
                document.getElementById("res_display_div").style.visibility = "hidden";
            }
            function show_result_image() {
                document.getElementById("res_display_div").style.visibility = "visible";
            }

            // Database load function adapted to get just page IDs at startup for next/prev switching, etc.
            function load_ids(data) {
            //clear text-entry box:
                document.getElementById("idText").innerHTML = "";
                lines = data.split("\n");
                for(i in lines) {
                    bits = lines[i].split(/[ ,]+/).filter(Boolean);
                    if (typeof bits[0] !== 'undefined') {
                        var id = "";
                        // chop initial ">" from fasta format
                        if(bits[0].charAt(0)==">") id = bits[0].substring(1);
                        else id = bits[0];
                    }
                    else {
                        console.log(i+" lines of data loaded!")
                        document.getElementById("messages").innerHTML = "";
                    }
                    lines[i] = bits[0]; // just get the ids!
                }
            }

            function get_database_ids(the_db){
                hide_query_panel();
                hide_display_panel();
                if(!the_db) the_db = document.getElementById("maw_data").value;
                db_name = the_db;
                console.log("database: "+the_db);
                $.ajax({
                    type: "GET",
                    url: "get_database.php",
                    data: {database: the_db},
                    success: function(data) {
                        load_ids(data);
                        show_query_panel();
                    }
                });
            }

    /*********** Utility functions: ***********/
            function storageAvailable(type) {
                try {
                    var storage = window[type],
                        x = '__storage_test__';
                    storage.setItem(x, x);
                    storage.removeItem(x);
                    return true;
                }
                catch(e) {
                    return e instanceof DOMException && (
                        // everything except Firefox
                        e.code === 22 ||
                        // Firefox
                        e.code === 1014 ||
                        // test name field too, because code might not be present
                        // everything except Firefox
                        e.name === 'QuotaExceededError' ||
                        // Firefox
                        e.name === 'NS_ERROR_DOM_QUOTA_REACHED') &&
                        // acknowledge QuotaExceededError only if there's something already stored
                        storage.length !== 0;
                }
            }
            function getFormattedDate() {
                var date = new Date();
                var month = date.getMonth() + 1;
                var day = date.getDate();
                var hour = date.getHours();
                var min = date.getMinutes();
                var sec = date.getSeconds();
                month = (month < 10 ? "0" : "") + month;
                day = (day < 10 ? "0" : "") + day;
                hour = (hour < 10 ? "0" : "") + hour;
                min = (min < 10 ? "0" : "") + min;
                sec = (sec < 10 ? "0" : "") + sec;
                var str = date.getFullYear() + "-" + month + "-" + day + "_" +  hour + ":" + min + ":" + sec;
                return str;
            }
            function getRandomIntInclusive(min, max) {
              min = Math.ceil(min);
              max = Math.floor(max);
              return Math.floor(Math.random() * (max - min + 1)) + min;
            }
        // From: https://gist.github.com/gordonbrander/2230317
            function uniqueID(){
              function chr4(){
                return Math.random().toString(16).slice(-4);
              }
              return chr4() + chr4() +
                '-' + chr4() +
                '-' + chr4() +
                '-' + chr4() +
                '-' + chr4() + chr4() + chr4();
            }

            function log_user_choice(query_id,target_id,result_num,database) {
                var the_time = getFormattedDate();
                var sim_id="sim"+result_num;
                var sim_choice = document.getElementById(sim_id).value;
                var reportString = the_time+"\t";
                if(can_store_UID) {
                    reportString += localStorage.getItem("userID");
                }
                else {
                    reportString += UID;
                }
                reportString += "\t"
                            +query_id + "\t"
                            +target_id + "\t"
                            +sim_choice + "\t"
                            + database + "\t"
                            + 'rank: ' + result_num + ": " + document.getElementById('rank_toggle_button').value;
                $.ajax({
                    type: "POST",
                    url: "log_user_interaction.php",
                    data: {reportString: reportString},
                    dataType:'TEXT',
                    success: function(response){
                        console.log("PHP received: "+response);
                    }
                });
            }

        // Server-side - user need never be aware of this; log needs to be on remote server - see log_search_problem.php
            function log_search_problem(query_id,message,database) {
                var the_time = getFormattedDate();
                var reportString = the_time+"\t";
                if(can_store_UID) {
                    reportString += localStorage.getItem("userID");
                }
                else {
                    reportString += UID;
                }
                reportString += "\t" +query_id+"\t" + database+"\t" + message;
                $.ajax({
                    type: "POST",
                    url: "log_search_problem.php",
                    data: {reportString: reportString},
                    dataType:'TEXT',
                    success: function(response){
                        console.log("PHP received: "+response);
                        // put on console what server sent back...
                    }
                });
            }

    // Client-side UI stuff
            function checkKey(e) {
                e = e || window.event;
                if([37, 38, 39, 40].indexOf(e.keyCode) > -1) {
                    e.preventDefault();
                }
                if (e.keyCode == '38') {    // up arrow
                    if(highlighted_result_row>0) {
                        var targetID="result_row"+(highlighted_result_row-1);
                        document.getElementById(targetID).click();
                    }
                }
                else if (e.keyCode == '40') {    // down arrow
                    if(highlighted_result_row<last_res_disp) {
                        var targetID="result_row"+(highlighted_result_row+1);
                    }
                    else {
                        var targetID="result_row0";
                    }
                    document.getElementById(targetID).click();
                }
  /*
                else if (e.keyCode == '37') {    // left arrow - repeat last search
                    if(last_query_id.length) {
                        document.getElementById("idText").value = last_query_id;
                        document.getElementById("id_searchButton").click();
                    }
                }
  */
                else if (e.keyCode == '37') {    // left arrow - Search previous page
                    find_page_id(false);
                    query_id = document.getElementById("idText").value;
                    do_search(query_id,jaccard,num_res_disp);
                }
                else if (e.keyCode == '39') {    // right arrow - Search next page
                    find_page_id(true);
                    query_id = document.getElementById("idText").value;
                    do_search(query_id,jaccard,num_res_disp);
                }
               else if (e.ctrlKey && (e.keyCode == '79')) {
                    console.log("Control + O pressed");
            //        load_arbitrary_database();
                    load_full_maws_database();
                }
            }

        // Unused??
            var dl_text =  "";
          // Unused??
            function PreviewText() {
                var oFReader = new FileReader();
                oFReader.readAsText(document.getElementById("uploadText").files[0]);
                oFReader.onload = function (oFREvent) {
                    dl_text = document.getElementById("uploadTextValue").value = oFREvent.target.result;
                    // We now have it so can delete the hidden input value
                    document.getElementById("uploadTextValue").value = "";
                };
            };

        // Client-side
            $(document).ready(function(){
                $('#img_display').zoom();
                $('#result_img_display').zoom();
                $('#searchButton').click(function ()
                {
                    query_id = document.getElementById("idText").value;
                    do_search(query_id,jaccard,num_res_disp);
                });
                $('#id_searchButton').click(function ()
                {
                    query_id = document.getElementById("idText").value;
                    do_search(query_id,jaccard,num_res_disp);
                });
                $('#random_searchButton').click(function ()
                {
                    document.getElementById("idText").value = lines[getRandomIntInclusive(0, lines.length)];
                    query_id = document.getElementById("idText").value;
                    do_search(query_id,jaccard,num_res_disp);
                });
            });

        // Client-side, though this needs to interact with server, as book data will be on server, not client
            function find_book_id(next) {
                var this_id = document.getElementById("idText").value;
                if(this_id == null) return false;
                else {
                    for(var i=0;i<lines.length;i++) {
                        if((lines[i].startsWith(">"+this_id))||(lines[i].startsWith(this_id))) {
                            break;
                        }
                    }
                    if(((i==0)&&(!next))||((i==lines.length)&&(next))) return;
                // now find next/prev item starting with a different id
                    var this_book = "";
                    var id_segs =  this_id.split("_");
                    for(j=0;j<id_segs.length-3;j++) this_book+=id_segs[j]+"_";
                    this_book+=id_segs[j];
                    var new_id = "";
                    var new_book = "";
                    if(next) {
                        for(;i<lines.length;i++) {
                            new_book = "";
                            new_id = lines[i].split(/[ ,]+/).filter(Boolean)[0];
                            id_segs =  new_id.split("_");
                            for(j=0;j<id_segs.length-3;j++) new_book+=id_segs[j]+"_";
                            new_book+=id_segs[j];
                            if(new_book.startsWith(">")) new_book = new_book.substring(1);
                            if(new_book != this_book) {
                                break;
                            }
                        }
                    }
                    else {
                        for(;i>0;i--) {
                            // FIXME - stand by for messy recursion!!
                            new_book = "";
                            new_id = lines[i].split(/[ ,]+/).filter(Boolean)[0];
                            id_segs =  new_id.split("_");
                            for(j=0;j<id_segs.length-3;j++) new_book+=id_segs[j]+"_";
                            new_book+=id_segs[j];
                            if(new_book.startsWith(">")) new_book = new_book.substring(1);
                            if(new_book != this_book) {
                                // now we are at last image of the previous book,
                                // so find the book before that one
                                // and go to next image - it will be the first of the book we want!
                                this_book = new_book;
                                for(;i>0;i--) {
                                    new_book = "";
                                    new_id = lines[i].split(/[ ,]+/).filter(Boolean)[0];
                                    id_segs =  new_id.split("_");
                                    for(j=0;j<id_segs.length-3;j++) new_book+=id_segs[j]+"_";
                                    new_book+=id_segs[j];
                                    if(new_book.startsWith(">")) new_book = new_book.substring(1);
                                    if(new_book != this_book) {
                                        if(i>0) i++; //Don't go to next if at first
                                        new_id = lines[i].split(/[ ,]+/).filter(Boolean)[0];
                                        break;
                                    }
                                }
                                break;
                            }
                        }
                    }
                }
                new_id = lines[i].split(/[ ,]+/).filter(Boolean)[0];
                if(new_id.startsWith(">")) new_id = new_id.substring(1);
                document.getElementById("idText").value = new_id;

                query_id = new_id;
                load_page_query(new_id);
            }

            function find_page_id(next) {
                var this_id = document.getElementById("idText").value;
                if(this_id == null) return false;
                else {
                    for(var i=0;i<lines.length;i++) {
                        if((lines[i].startsWith(">"+this_id))||(lines[i].startsWith(this_id))) {
                            break;
                        }
                    }
                    if(((i==0)&&(!next))||((i==lines.length)&&(next))) return;
                    var new_id = "";
                    if(next) {
                        new_id = lines[i+1].split(/[ ,]+/).filter(Boolean)[0];
                    }
                    else {
                        new_id = lines[i-1].split(/[ ,]+/).filter(Boolean)[0];
                    }
                }
                if(new_id.startsWith(">")) new_id = new_id.substring(1);
                document.getElementById("idText").value = new_id;

                query_id = new_id;
                load_page_query(new_id)
            }

            function change_num_res() {
                num_res_disp = document.getElementById("res_disp_select").value;
            }

            function switch_rank() {
                if(jaccard) {
                    document.getElementById("rank_toggle").innerHTML = "Basic";
                    jaccard = false;
                }
                else {
                    document.getElementById("rank_toggle").innerHTML = "Jaccard distance";
                    jaccard = true;
                }
                do_search(query_id,jaccard,num_res_disp);
            }
            function initialise() {
                get_database_ids('maw_4-8_sameline.txt');
                document.getElementById("rank_toggle").innerHTML = (jaccard)? "Jaccard distance" : "Basic";
            }


        </script>
  </head>

  <body onload="initialise();">

    <script>
      document.onkeydown = checkKey;
    </script>
    <hr>

    <div id="query_panel">
      <table id="query_table">
        <tr>
          <td class="search_panel">
            <span>
              <b>Search: &nbsp;&#160;&#160;</b>
              <span class="tooltip">
                <img class="prev_book zoom" onclick="find_book_id(false);do_search(query_id,jaccard,num_res_disp);"
                     src="prev_book.png">
			    <span class="tooltiptext">Go to prev book and search</span>
              </span>
              <span class="tooltip">
                <img class="prev_page zoom" onclick="find_page_id(false);do_search(query_id,jaccard,num_res_disp);"
                     src="prev_page.png">
              <span class="tooltiptext">Search for prev page</span>
              </span>
              <textArea onchange="show_query_display();" id="idText"></textArea>
              <span class="tooltip">
                <button calss="zoom" id="id_searchButton">Go</button>
                <span class="tooltiptext">Search for current page</span>
              </span>
              <span class="tooltip">
                <img class="next_page zoom" onclick="find_page_id(true);do_search(query_id,jaccard,num_res_disp);"
                     src="next_page.png">
			    <span class="tooltiptext">Search for next page</span>
              </span>
              <span class="tooltip">
                <img class="next_book zoom" onclick="find_book_id(true);do_search(query_id,jaccard,num_res_disp);"
                     src="next_book.png">
			    <span class="tooltiptext">Go to next book and search</span>
              </span>
            </span>
            &nbsp;&#160;&#160;
          </td>

          <td class="random_panel">
            <span>
              &nbsp;&#160;&#160;
              <button id="random_searchButton" >Random Search</button>
              &nbsp;&#160;&#160;
            </span>
          </td>

          <td class="messages_panel">
            <span id="messages"></span>
          </td>

          <td class="ranking_panel">
            <span>
              &nbsp;&nbsp;Result ranking:
              <span id='rank_toggle'></span>
              &nbsp;
              <button id='rank_toggle_button'
                      onclick='switch_rank();'>Change</button>
            </span>
          </td>

          <td class="res_num_panel" style="background-color:SandyBrown">
            <span>
              Results to display
              <select class="drop_downs" id="res_disp_select"
                      name="num_to_disp"
                      onchange="change_num_res();">
                <option>5</option>
                <option>10</option>
                <option selected>15</option>
                <option>20</option>
                <option>25</option>
                <option>30</option>
              </select>
            </span>
          </td>
        </tr>
      </table>
    </div>

    <hr>

    <div id="display_panel">
      <div>
        <div id="query_display">
          <span class="tooltip">
            <img onclick="find_book_id(false);"
                  class="prev_book" src="prev_book.png">
            <span class="tooltiptext">Show first page of prev book</span>
          </span>
          <span class="tooltip">
            <img onclick="find_page_id(false);"
                  class="prev_page" src="prev_page.png">
            <span class="tooltiptext">Show prev page</span>
          </span>
          <span style="color:green" id="q_page_display"></span>
          <span class="tooltip">
            <img onclick="find_page_id(true);"
                  class="next_page" src="next_page.png">
            <span class="tooltiptext">Show next page</span>
          </span>
          <span class="tooltip">
            <img onclick="find_book_id(true);"
                  class="next_book" src="next_book.png">
            <span class="tooltiptext">Show first page of next book</span>
          </span>
        </div>
        <div id="img_display"></div>
        <div id="q_text_display"></div>
      </div>
      &nbsp;&#160;&#160;
      <div>
        <span id="res_table_label"></span>
        <div id="res_table_div"></div>
      </div>
      &nbsp;&#160;&#160;
      <div id="res_display_div">
        <div id="result_id_msg"></div>
        <div id="result_img_display"></div>
        <div id="t_text_display"></div>
      </div>
    </div>
  </body>
</html>
