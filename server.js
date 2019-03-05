/*******************************************************************************
 * Imports 
 ******************************************************************************/
const express = require('express');
const mustacheExpress = require('mustache-express');
const request = require('request');
const fileUpload = require('express-fileupload');
const fs = require('fs');
const cp = require('child_process');
const path_app = require('path');

/*******************************************************************************
 * Globals / Inits
 ******************************************************************************/
const MAWS_DB = './data/emo_ids_maws.txt';
// const MAWS_DB = './data/dev_emo_ids_maws.txt'; // for dev only! quick rebuilds.
const DIAT_MEL_DB = './data/id_diat_mel_strs.txt'; // for dev only! quick rebuilds.
const EMO_IDS = []; // all ids in the system
const EMO_IDS_MAWS = {}; // keys are ids, values are an array of maws for that id
const EMO_IDS_DIAT_MELS = {};
const MAWS_to_IDS = {}; // keys are maws, values are an array of all ids for which that maw appears
const NGRAMS_to_IDS = {}; // keys are ngrams, values are a array of all ids for which that ngram appears
const word_totals = []; // total words per id, used for normalization

let query_id;
var threshold = false; // default until supplied
let working_path;

const app = express();

/*******************************************************************************
 * Setup
 ******************************************************************************/

load_maws(); // load the MAWS 
load_diat_mels(); // load the diatonic melodies
// load_ngram_database(9);   // Just a magic number that seems to work

const port = 8000;
app.listen(
    port,
    () => console.log('EMO app listening on port 8000!') // success callback
);

app.engine('html', mustacheExpress()); // render html templates using Mustache
app.set('view engine', 'html'); 
app.set('views', './templates'); 

app.use(express.static('static')); // serve static files out of /static
app.use(fileUpload()); // file upload stuff

/*******************************************************************************
 * Request handlers 
 ******************************************************************************/

app.get('/', function (req, res) { 
    res.render('index');
});


app.get('/compare', function (req, res) { 
    // console.log(req.query.qid); console.log(req.query.mid);

    // q for 'query', m for 'match'
    const q_id = req.query.qid;
    const m_id = req.query.mid;

    if (!q_id || !m_id) { return res.status(400).send('q_id or m_id must be provided!'); }

    const base_img_url = 'http://doc.gold.ac.uk/~mas01tc/page_dir_50/';
    const img_ext = '.jpg';
	const q_jpg_url = base_img_url + q_id + img_ext;
	const m_jpg_url = base_img_url + m_id + img_ext;

    const base_mei_url = 'http://doc.gold.ac.uk/~mas01tc/EMO_search/mei_pages/';
    const mei_ext = '.mei';
	const q_mei_url = base_mei_url + q_id + mei_ext;
	const m_mei_url = base_mei_url + m_id + mei_ext;


    // id_diat_mel_lookup is a file of ids and codestrings
    // this finds the line of the query and result pages
	const q_diat_str = EMO_IDS_DIAT_MELS[q_id];
	const m_diat_str = EMO_IDS_DIAT_MELS[m_id];

    const ngr_len = 5;

    if(!q_diat_str.length) return false;

    q_ngrams = [];
    if(q_diat_str.length < ngr_len) {
        q_ngrams.push(q_diat_str + "%");
    } else if (q_diat_str.length == ngr_len) {
        q_ngrams.push(q_diat_str);
    } else {  
        for(i = 0; i + ngr_len <= q_diat_str.length; i++) {
            q_ngrams.push(q_diat_str.substr(i, ngr_len));
        }
    }

    var q_common_ngram_locations = [];
    var m_common_ngram_locations = [];

    for(i = 0; i <= q_ngrams.length; i++) {
        var loc = m_diat_str.indexOf(q_ngrams[i]);
        if(loc >= 0) {
            q_common_ngram_locations.push(i);
            m_common_ngram_locations.push(loc);
        }
    }

    request(q_mei_url, function (error, response, q_mei) { if (!error && response.statusCode == 200) {
    request(m_mei_url, function (error, response, m_mei) { if (!error && response.statusCode == 200) {
        // console.log(q_mei); console.log(m_mei);
        const data = {
            colour: 'blue',
            q_id,
            m_id,
            q_jpg_url,
            m_jpg_url,
            q_mei: q_mei.replace(/(\r\n|\n|\r)/gm,''), // strip newlines
            m_mei: m_mei.replace(/(\r\n|\n|\r)/gm,''), // strip newlines
            ngr_len,
            q_common_ngram_locations,
            m_common_ngram_locations,
        }
        res.render('compare', data);
    } else { return res.status(400).send('Could not find the MEI file for m_id'); }});
    } else { return res.status(400).send('Could not find the MEI file for q_id'); }});

});


// Returns an array of all emo ids
app.get('/api/emo_ids', function (req, res) { res.send(EMO_IDS); });

// Handle a query
app.get('/api/query', function (req, res) {
    let num_results = 20; // Default
    threshold = false; // Default

    let jaccard = false;
    if (req.query.jaccard == 'true') { jaccard = true; } // TODO(ra): fix this in the request - POST not get...

    if(req.query.num_results) {
        num_results = parseInt(req.query.num_results);
    }

    if(req.query.threshold !== undefined) {
        const raw_t = req.query.threshold;
        if (raw_t === 'false') { threshold = false; }
        else { threshold = parseFloat(raw_t); }
    }

    let result;
    if(req.query.qstring) {
        console.log('Querying by string...');
        const query = req.query.qstring;
        result = search('words', query, jaccard, num_results);
    } else if(req.query.id) {
        console.log('Querying by id...');
        result = search('id', req.query.id, jaccard, num_results);

    } else if(req.query.diat_int_code) {
        console.log('q diat_int_code');
        result = search_with_code(req.query.diat_int_code, jaccard, num_results);
    }

    // console.log(result)  
    res.send(result);
});

// Handle image uploads
// TODO(ra): validate supported file formats
app.post('/api/image_query', function(req, res) {
    if (!req.files) { return res.status(400).send('No files were uploaded.'); }

    // The name of the input field is used to retrieve the uploaded file
    let user_image = req.files.user_image;

    working_path = './run/';

    // Use the mv() method to save the file there
    user_image.mv(working_path + user_image.name, (err) => {
        if (err) { return res.status(500).send(err); }
        else {
            console.log("Uploaded file saved as " + working_path + user_image.name);
            const ngram_search = false; // TODO(ra): make this work!
            const result = run_image_query(user_image, ngram_search);
            res.send(result);
        }
    });
});

function run_image_query(user_image, ngram_search) {
    const jaccard = true; // TODO(ra) should probably get this setting through the POST request, too...
    const num_results = 20; // TODO(ra) should probably get this setting through the POST request, too...

    let query_data;
    let query;
    let result;
    if(!ngram_search) {
        query_data = cp.execSync('./callout_scripts/temp_image_to_maws.sh ' + user_image.name + ' ' + working_path);
        query = String(query_data); // a string of maws, preceded with an id
        result = search('words', query, jaccard, num_results);
    }
    else {
        query_data = cp.execSync('./callout_scripts/image_to_ngrams.sh ' + user_image.name + ' ' + working_path + ' ' + '9');
        query = String(query_data);
        result = search('words', query, jaccard, num_results, true);
    }

    result.unshift(path_app.basename(working_path) + '/' + user_image.name); // add path/filename to beginning of array
    return result;
}


/*******************************************************************************
 * Query functions
 ******************************************************************************/

// method can be 'id' or 'words'
// query is a string, either holding the id a id+maws line
function search(method, query, jaccard, num_results, ngram) {
    if (ngram === undefined) { ngram = false; }

    if(!query) { // Need to report this back to browser/user
        console.log("No query provided!");
        return false;
    }

    let words;
    if (method === 'id') {
        if (!(query in EMO_IDS_MAWS)) { // TODO: need to report to frontend 
            console.log("ID " + query + " not found in " + MAWS_DB);
            return;
        }
        words = EMO_IDS_MAWS[query];
    } else if (method === 'words') {
        parsed_line = parse_id_maws_line(query);
        words = parsed_line.words;
    }

    let signature_to_ids_dict;
    if (ngram) { signature_to_ids_dict = NGRAMS_to_IDS; }
    else { signature_to_ids_dict = MAWS_to_IDS; }

    return get_result_from_words(words, signature_to_ids_dict, jaccard, num_results);
}

function search_with_code(str, jaccard, num_results) {
    working_path = cp.execSync('/home/mas01tc/emo_search/web-demo/set_working_path.sh') + '/';
    const query_data = cp.execSync('/home/mas01tc/emo_search/web-demo/codestring_to_maws.sh ' + str + ' ' + working_path);
    const query_str = String(query_data); // a string of maws, preceded with an id

    const result = search('words', query_str, jaccard, num_results);
    result.unshift("code query");
    return result;
}

function get_result_from_words(words, signature_to_ids_dict, jaccard, num_results) {
    if (words.length < 6) { // TODO: Need to report to frontend
        console.log("Not enough words in query.");
        return [];
    }
    const scores = get_scores(words, signature_to_ids_dict);
    const scores_pruned = get_pruned_and_sorted_scores(scores, words.length, jaccard);
    const result = gate_scores_by_threshold(scores_pruned, threshold, jaccard, num_results);
    return result;
}

function get_scores(words, signature_to_ids_dict) {
    var res = false;
    var scores = {};

    for (const word of words) {
        const ids = signature_to_ids_dict[word]
        if (!ids) { continue; }

        for(const id of ids) {
            if (!scores[id]) { scores[id] = 0; }
            scores[id]++;
        }
    }
    return scores;
}



function get_pruned_and_sorted_scores(scores, wds_in_q, jaccard) {
    var scores_pruned = [];

    // Prune
    for (var id in scores) {
        if (!scores.hasOwnProperty(id)) { continue; }
        const num = scores[id];
        if(num > 1) {
            result = {};

            const num_words = word_totals[id];

            result.id = id;
            result.num = num;
            result.num_words = num_words;

            result.jaccard = 1 - (num / (num_words + wds_in_q - num));
            scores_pruned.push(result);
        }
    }

    // Sort
    if (jaccard) {
        // Ascending, as 0 is identity match
        scores_pruned.sort((a, b) => { return a.jaccard - b.jaccard; }); 
    }
    else {
        // Descending
        scores_pruned.sort((a, b) => { return b.num - a.num; });
    }

    return scores_pruned;
}

function gate_scores_by_threshold(scores_pruned, threshold, jaccard, num_results) {
    // if threshold is set in URL, stop returning results when delta < threshold
    if (threshold) {
        const out_array = [];
        out_array[0] = scores_pruned[0];  // the identity match, or at least the best we have
        for(var p = 1; p < scores_pruned.length; p++) {
            var delta = 0;
            if (jaccard) { delta = jacc_delta(scores_pruned, p); }
            else { delta = scores_pruned[p - 1].num - scores_pruned[p].num; }
            if (threshold == "median") { threshold = 0 + getMedian(scores_pruned, jaccard); }
            if (delta >= threshold) {
                out_array[p] = scores_pruned[p];
                out_array[p].delta = delta;
            } else {
                num_results = p - 1;
                break;
            }
        }
        return out_array;
    } else {
        // return the first num_results results
        return scores_pruned.slice(0, num_results);
    }
}


/*******************************************************************************
 * Data loading
 ******************************************************************************/

function load_maws() { load_file(MAWS_DB, parse_maws_db); }
function load_diat_mels() { load_file(DIAT_MEL_DB, parse_diat_mels_db); }

function parse_maws_db(data_str) {  
    const lines = data_str.split("\n");
    console.log(lines.length + " lines of MAWs to read...");

    const no_maws_ids = [];
    for (const line of lines) {
        if (line) {
            parsed_line = parse_id_maws_line(line);
            const id = parsed_line.id;
            const words = parsed_line.words;

            if (words === undefined) { // TODO(ra): how should we handle these? 
                no_maws_ids.push(id);
                continue;
            }

            EMO_IDS.push(id);           
            EMO_IDS_MAWS[id] = words; 

            word_totals[id] = words.length;
            for (const word of words) {
                if (!MAWS_to_IDS[word]) { MAWS_to_IDS[word] = []; }
                MAWS_to_IDS[word].push(id);
            }
        }
    }

    // fs.writeFile("./run/err.log", no_maws, () => {}); // write out ids with no maws
    // console.log(no_maws);
    // console.log(EMO_IDS);
    // console.log(EMO_IDS_MAWS);
    console.log(EMO_IDS.length + " lines of MAW data loaded!");
}

function parse_diat_mels_db(data_str) {
    const lines = data_str.split("\n");
    console.log(lines.length + " lines of diatonic melody strings to read...");
    for (const line of lines) {
        if (line) {
            const [id, diat_mels_str] = line.split(/ (.+)/); // splits on first match of whitespace
            EMO_IDS_DIAT_MELS[id] = diat_mels_str;
        }
    }
    console.log("Diatonic melody strings loaded!");
}

function load_file(file, data_callback) {
    // The 'db' is a text file, where each line is an EMO page ID,
    // followed by the MAWs for that page.

    console.log("Loading " + file);
    fs.readFile(file, 'utf8', (err, data) => {
        if (err) { throw err; }

        if (!data.length) {
            console.log("No data!");
        } else {
            data_callback(data);
        }
    });
}





/*******************************************************************************
 * Helpers
 ******************************************************************************/

function jacc_delta (array, n) {
    return array[n].jaccard - array[n - 1].jaccard;
}

function jacc_delta_log (array, n) {
    return Math.log(array[n].jaccard) - Math.log(array[n - 1].jaccard);
}

function console_sample(array, num, str) {
    console.log("Sampling array " + str + " - " + num + " entries");
    for(var i = 0;i < num;i++) {
        console.log(i + ". " + array[i].id);
    }
}

function getMedian(array, jaccard){
    var values = [];
    if(jaccard == "true") {
        for(let i = 0;i < array.length;i++) {
            values.push(array[i].jaccard);
        }
    }
    else { 
        for(let i = 0;i < array.length;i++) {
            values.push(array[i].num);
        }
    }
    values.sort((a, b) => a - b);
    let median = (values[(values.length - 1) >> 1] + values[values.length >> 1]) / 2;
    console.log("Median = " + median);
    return median;
}

function parse_id_maws_line(line) {
    parsed_line = {};

    let [id, maws_str] = line.split(/ (.+)/); // splits on first match of whitespace

    if (id.charAt(0) === '>') { id = id.substring(1); } // remove leading > if it's there
    parsed_line.id = id;

    if (maws_str === undefined) { return parsed_line; }

    const words = maws_str.split(/[ ,]+/).filter(Boolean); // splits rest into words by whitespace
    parsed_line.words = words;
    return parsed_line;
}

/*******************************************************************************
 * ngram stuff, unused but leaving here for now... 
 ******************************************************************************/
// var ng_lines = [];
//
//
// function load_ngram_database(n) {
//  var ng_len = n;
//      if((ng_len>2)&&(ng_len<16)) {
//      db_name ="./data/emo_data/databases/ngrams/emo_"+ng_len+"grams.txt";
//      console.log("Trying to load "+db_name);
//      get_and_load_ngram_database(db_name);
//  }
//  else alert("Loading ngram database failed!");
//}
// function get_and_load_ngram_database(db_name) {
//      console.log("Actually loading "+db_name);
//  fs.readFile(db_name,'utf8',(err,data) => {
//      if (err) {
//          throw err;
//      }
//      if(!data.length) console.log("No data!!");
//      else {
//          console.log("Loading "+data.length+" of ngram data")
//          load_ngram_data(data);
//      }
//  })
// }

// function load_ngram_data(data) {
//  ng_lines = data.split("\n");
//     console.log(ng_lines.length+" lines of ngrams to read");
//  for(i in ng_lines) {
//      bits = ng_lines[i].split(/[ ,]+/).filter(Boolean);
//      if (typeof bits[0] !== 'undefined') {
//          var id = "";
//          // chop initial ">" from fasta format
//          if(bits[0].charAt(0)==">") id = bits[0].substring(1);
//          else id = bits[0];
//          word_totals[id] = bits.length - 1;
//          for(j=1;j<bits.length;j++) {
//              ng_trie.id_add(bits[j],id);
//          }
//      }
//      else {
//          console.log("End of ngram data")
//      }
//  }
//  console.log(i+" lines of ngram data loaded!");
//  console.log("Ngrams initialised");
// }
