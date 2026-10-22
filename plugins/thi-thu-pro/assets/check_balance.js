const fs = require('fs');

const file = 'c:/xampp/htdocs/wordpress/wp-content/plugins/traffic-quiz-pro/assets/tqp-script.js';
let content = fs.readFileSync(file, 'utf8');

let count = 0;
let lines = content.split('\n');
let mismatchedLines = [];

for (let i = 0; i < lines.length; i++) {
    for (let char of lines[i]) {
        if (char === '{') count++;
        if (char === '}') count--;
    }
}

console.log("Final balance: " + count);
