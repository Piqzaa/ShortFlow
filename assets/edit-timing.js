export function layoutClips(clips) {
  let cursor=0;
  return clips.map(clip=>{const rate=Number(clip.rate||1),at=Number(clip.at??cursor);cursor=at+(clip.end-clip.start)/rate;return {...clip,at,rate};}).sort((a,b)=>a.at-b.at);
}
export function timelineDuration(clips) {return layoutClips(clips).reduce((end,clip)=>Math.max(end,clip.at+(clip.end-clip.start)/clip.rate),0);}
export function canPlace(clips, keys, delta) {
  const moved=layoutClips(clips).map(clip=>({...clip,at:clip.at+(keys.includes(clip.key)?delta:0)})).sort((a,b)=>a.at-b.at);
  return moved.every((clip,index)=>clip.at>=0 && clip.at+(clip.end-clip.start)/clip.rate<=179.5 && (!index||clip.at>=moved[index-1].at+(moved[index-1].end-moved[index-1].start)/moved[index-1].rate-.001));
}

// Map caption time through source fragments. A split retains its source occurrence.
export function remapCaptions(rows, before, after) {
  const result=[];
  for(const row of rows) {
    const parts=[];let oldOffset=0;
    for(const old of layoutClips(before)) {
      oldOffset=old.at;
      const start=Math.max(row.start,oldOffset),end=Math.min(row.end,oldOffset+(old.end-old.start)/old.rate);
      let newOffset=0;
      for(const next of layoutClips(after)) {
        newOffset=next.at;
        const same=old.id===next.id&&(!old.origin||!next.origin||old.origin===next.origin);
        if(same&&end>start) {
          const first=Math.max(old.start+(start-oldOffset)*old.rate,next.start),last=Math.min(old.start+(end-oldOffset)*old.rate,next.end);
          if(last-first>0.0005)parts.push({start:Math.round((newOffset+(first-next.start)/next.rate)*1000)/1000,end:Math.round((newOffset+(last-next.start)/next.rate)*1000)/1000,text:row.text,...(row.x!==undefined?{x:row.x}:{}),...(row.y!==undefined?{y:row.y}:{})});
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
