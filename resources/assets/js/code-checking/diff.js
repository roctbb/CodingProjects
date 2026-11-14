// Bounded line diff: long files use common edges and a replaced middle.
export function diffLines(before, after) {
    const a = before.split('\n'), b = after.split('\n'), output = [];
    const push = (type,text) => output.push({type,text});
    let start = 0, endA = a.length, endB = b.length;
    while (start < endA && start < endB && a[start] === b[start]) {push('same',a[start]); start++;}
    while (endA > start && endB > start && a[endA-1] === b[endB-1]) {endA--; endB--;}
    const rows = endA-start, columns = endB-start;
    if (rows*columns > 250000) {
        for (let i=start;i<endA;i++) push('removed',a[i]);
        for (let j=start;j<endB;j++) push('added',b[j]);
    } else {
        const matrix = Array.from({length:rows+1},() => new Uint32Array(columns+1));
        for (let i=rows-1;i>=0;i--) for (let j=columns-1;j>=0;j--) matrix[i][j] = a[start+i] === b[start+j] ? matrix[i+1][j+1]+1 : Math.max(matrix[i+1][j],matrix[i][j+1]);
        let i=0,j=0;
        while (i<rows || j<columns) {
            if (i<rows && j<columns && a[start+i] === b[start+j]) {push('same',a[start+i]);i++;j++;}
            else if (i<rows && (j===columns || matrix[i+1][j]>=matrix[i][j+1])) push('removed',a[start+i++]);
            else push('added',b[start+j++]);
        }
    }
    for (let i=endA;i<a.length;i++) push('same',a[i]);
    return output;
}
