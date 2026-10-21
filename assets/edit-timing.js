// Map caption time through source fragments. A split retains its source occurrence.
export function remapCaptions(rows, before, after) {
  const result=[];
  for(const row of rows) {
    const parts=[];let oldOffset=0;
    for(const old of before) {
      const start=Math.max(row.start,oldOffset),end=Math.min(row.end,oldOffset+old.end-old.start);
      let newOffset=0;
      for(const next of after) {
        const same=old.id===next.id&&(!old.origin||!next.origin||old.origin===next.origin);
        if(same&&end>start) {
          const first=Math.max(old.start+start-oldOffset,next.start),last=Math.min(old.start+end-oldOffset,next.end);
          if(last-first>0.0005)parts.push({start:Math.round((newOffset+first-next.start)*1000)/1000,end:Math.round((newOffset+last-next.start)*1000)/1000,text:row.text,...(row.x!==undefined?{x:row.x}:{}),...(row.y!==undefined?{y:row.y}:{})});
        }
        newOffset+=next.end-next.start;
      }
      oldOffset+=old.end-old.start;
    }
    parts.sort((a,b)=>a.start-b.start);const merged=[];
    for(const part of parts) {
      const last=merged.at(-1);
      if(last&&Math.abs(last.end-part.start)<0.002)last.end=part.end;else merged.push(part);
    }
    result.push(...merged);
  }
  return result.sort((a,b)=>a.start-b.start);
}
