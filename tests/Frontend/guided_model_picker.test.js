import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';
import {GuidedModelPicker} from '../../assets/guided-model-picker.js';

function picker(name) {
  const source=readFileSync(new URL(`../../assets/controllers/${name}_controller.js`,import.meta.url),'utf8').replace("import { Controller } from '@hotwired/stimulus';",'').replace("import { GuidedModelPicker } from '../guided-model-picker.js';",'').replace('export default class','class Picker');
  const Type=runInNewContext(`${source}\nPicker`,{Controller:class {},GuidedModelPicker});
  const c=new Type();
  c.modelsValue={a:{name:'Alpha',provider:'fal',quality:'720p',seconds:[5],ratios:['9:16']},a1080:{name:'Alpha',provider:'fal',quality:'1080p',seconds:[5],ratios:['9:16']},b:{name:'Beta',provider:'replicate',quality:'720p',seconds:[5],ratios:['9:16']},image:{name:'Image',provider:'fal',quality:'720p',image:true},frames:{name:'Frames',provider:'fal',quality:'1080p',image:true,endImageField:'end_image_url',end:true}};
  c.connectedValue={'fal-api':true,'fal-mcp':false,fal:true,replicate:false}; c.providersValue={fal:'Fal',replicate:'Replicate'};
  c.hasSourceTarget=true; c.sourceTarget={value:'text'}; c.hasAccessTarget=true; c.accessTarget={value:'api'};
  c.hasPickerTarget=true; c.hasPickerResultsTarget=true;
  c.cardTargets=[{dataset:{groupId:'alpha',variants:'["a","a1080"]'}},{dataset:{groupId:'beta',variants:'["b"]'}},{dataset:{groupId:'image',variants:'["image","frames"]'}}];
  const option=(value,textContent='')=>({value,textContent,classList:{toggle() {}}});
  c.groupTarget={value:'',options:[option(''),option('alpha','Alpha · Fal'),option('beta','Beta · Replicate'),option('image','Image · Fal')]};
  c.resolutionTarget={value:'',options:['',...Object.keys(c.modelsValue)].map(value=>option(value)),focus(){this.focused=true;}};
  c.pickerSearchTarget={value:''}; c.pickerResultsTarget={}; c.pickerCountTarget={};
  c.pickerResultTargets=c.cardTargets.map(card=>({dataset:{groupId:card.dataset.groupId,source:card.dataset.groupId==='image'?'image':'text'},querySelector:()=>null}));
  c.modelTarget={value:'a1080',dispatchEvent(event){this.lastEvent=event.type;}};
  return c;
}

for(const name of ['creation','series_settings']) {
  test(`${name}: picker keeps exact saved configuration and searches without changing it`,()=>{
    const c=picker(name);
    c.refreshPickerAvailability();
    assert.equal(c.groupTarget.value,'alpha');
    assert.equal(c.resolutionTarget.value,'a1080');
    assert.equal(c.modelTarget.value,'a1080');
    assert.equal(c.groupTarget.options[3].disabled,true);
    c.pickerSearchTarget.value='Beta'; c.searchPicker();
    assert.equal(c.pickerResultTargets[1].hidden,false);
    assert.equal(c.pickerResultTargets[0].hidden,true);
    assert.equal(c.modelTarget.value,'a1080');
    c.pickResult({currentTarget:c.pickerResultTargets[1]});
    assert.equal(c.modelTarget.value,'b');
    assert.equal(c.modelTarget.lastEvent,'change');
    assert.equal(c.resolutionTarget.focused,true);
  });
  test(`${name}: API registration never claims a browser connection and changing method keeps the model`,()=>{
    const c=picker(name);
    c.refreshPickerConnections();
    assert.equal(c.groupTarget.options[1].textContent,'Alpha · Fal');
    assert.match(c.connectionCaption(),/clé API enregistrée/);
    c.accessTarget.value='mcp'; c.refreshPickerConnections();
    assert.match(c.groupTarget.options[1].textContent,/à connecter/);
    assert.match(c.connectionCaption(),/navigateur à connecter/);
    assert.equal(c.modelTarget.value,'a1080');
    c.connectedValue['fal-mcp']=true; c.connectedValue['fal-api']=false;
    assert.equal(c.pickerConnected(c.modelsValue.a),true);
    c.accessTarget.value='api';
    assert.equal(c.pickerConnected(c.modelsValue.a),false);
    c.connectedValue.replicate=true;
    c.modelTarget.value='b'; c.accessTarget.value='mcp';
    assert.equal(c.pickerConnected(c.modelsValue.b),false);
    assert.match(c.connectionCaption(),/navigateur à connecter/);
  });
  test(`${name}: old unavailable selections are kept and explicit image choice respects ending capability`,()=>{
    const c=picker(name);
    c.modelTarget.value='old-unavailable'; c.syncPicker();
    assert.equal(c.modelTarget.value,'old-unavailable');
    assert.equal(c.resolutionTarget.disabled,true);
    c.sourceTarget.value='image'; c.hasUseEndTarget=true; c.useEndTarget={checked:true}; c.groupTarget.value='image';
    c.selectGroup();
    assert.equal(c.modelTarget.value,'frames');
    assert.equal(c.modelTarget.lastEvent,'change');
  });
}
