import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
  static targets = ['canvas', 'stage', 'fallback'];
  async connect() {
    this.disposed = false;
    this.motion = matchMedia('(prefers-reduced-motion: reduce)');
    this.onMotion = () => { if (this.motion.matches) this.cleanup(); };
    this.motion.addEventListener('change', this.onMotion);
    if (this.motion.matches || navigator.connection?.saveData) return;
    this.observer = new IntersectionObserver(entries => {
      if (entries.some(e => e.isIntersecting)) { this.observer.disconnect(); this.load(); }
    }, { rootMargin: '150px' });
    this.observer.observe(this.element);
  }
  async load() {
    try {
      const [THREE, { gsap }, { ScrollTrigger }] = await Promise.all([import('three'), import('gsap'), import('gsap/ScrollTrigger')]);
      if (this.disposed || this.motion.matches) return;
      gsap.registerPlugin(ScrollTrigger);
      const canvas = this.canvasTarget;
      const renderer = this.renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true, powerPreference: 'low-power' });
      renderer.setPixelRatio(Math.min(devicePixelRatio, 1.5));
      const scene = new THREE.Scene();
      const camera = new THREE.PerspectiveCamera(35, 1, 0.1, 100);
      camera.position.z = 10;
      const phone = new THREE.Group();
      scene.add(phone);
      this.resources = [];
      const roundedShape = (w,h,r) => {
        const shape = new THREE.Shape(), x=-w/2,y=-h/2;
        shape.moveTo(x+r,y);shape.lineTo(x+w-r,y);shape.quadraticCurveTo(x+w,y,x+w,y+r);shape.lineTo(x+w,y+h-r);shape.quadraticCurveTo(x+w,y+h,x+w-r,y+h);shape.lineTo(x+r,y+h);shape.quadraticCurveTo(x,y+h,x,y+h-r);shape.lineTo(x,y+r);shape.quadraticCurveTo(x,y,x+r,y);return shape;
      };
      const add = (geometry, material, x=0,y=0,z=0) => { const mesh=new THREE.Mesh(geometry,material);mesh.position.set(x,y,z);phone.add(mesh);this.resources.push(geometry,material);return mesh; };
      const bodyGeo = new THREE.ExtrudeGeometry(roundedShape(2.45,4.85,.34),{depth:.16,bevelEnabled:true,bevelSegments:4,steps:1,bevelSize:.045,bevelThickness:.045,curveSegments:24});
      add(bodyGeo,new THREE.MeshStandardMaterial({color:0x514a61,metalness:1,roughness:.28}));
      const screenGeo = new THREE.ShapeGeometry(roundedShape(2.31,4.69,.29),32);
      const uv=screenGeo.attributes.uv, pos=screenGeo.attributes.position;
      for(let i=0;i<uv.count;i++) uv.setXY(i,(pos.getX(i)+1.155)/2.31,(pos.getY(i)+2.345)/4.69);
      this.texture = new THREE.TextureLoader().load('/media/studio-poster.svg', () => { if (!this.disposed) this.renderOnce?.(); });
      this.texture.colorSpace=THREE.SRGBColorSpace;
      const screenMat=new THREE.MeshBasicMaterial({map:this.texture});
      add(screenGeo,screenMat,0,0,.215);
      add(new THREE.ShapeGeometry(roundedShape(.78,.19,.095)),new THREE.MeshBasicMaterial({color:0x050507}),0,2.13,.23);
      add(new THREE.BoxGeometry(.045,.45,.1),new THREE.MeshStandardMaterial({color:0x71667d,metalness:1,roughness:.3}),1.27,.65,.08);
      add(new THREE.BoxGeometry(.045,.26,.1),new THREE.MeshStandardMaterial({color:0x71667d,metalness:1,roughness:.3}),-1.27,.9,.08);
      scene.add(new THREE.AmbientLight(0xffffff,2));
      const key = new THREE.DirectionalLight(0xe2d8ff,5);key.position.set(3,4,5);scene.add(key);
      const rim=new THREE.DirectionalLight(0x9664ff,6);rim.position.set(-3,-1,2);scene.add(rim);
      phone.rotation.set(.06,-.27,-.12);
      const pose={x:.06,y:-.27,z:-.12}; this.cursor={x:0,y:0};
      this.renderOnce=()=>{if(this.disposed)return;phone.rotation.set(pose.x+this.cursor.y,pose.y+this.cursor.x,pose.z);renderer.render(scene,camera);};
      this.resize=new ResizeObserver(()=>{ const {width,height}=this.stageTarget.getBoundingClientRect();renderer.setSize(width,height,false);camera.aspect=width/height;camera.updateProjectionMatrix();this.renderOnce(); });
      this.resize.observe(this.stageTarget);
      this.tween=gsap.to(pose,{y:.55,z:.18,x:.16,ease:'none',onUpdate:this.renderOnce,scrollTrigger:{trigger:this.element,start:'top top',end:'bottom top',scrub:1}});
      this.onPointer=e=>{this.cursor.x=(e.clientX/innerWidth-.5)*.12;this.cursor.y=(e.clientY/innerHeight-.5)*.06;this.renderOnce();};
      if(matchMedia('(pointer:fine)').matches) window.addEventListener('pointermove',this.onPointer,{passive:true});
      this.onContextLost=e=>{e.preventDefault();this.cleanup();};canvas.addEventListener('webglcontextlost',this.onContextLost);
      this.stageTarget.classList.add('ready');
      this.renderOnce();
      // Optional personal clip: only fetched when the scene is visible and motion is allowed.
      const source=this.element.dataset.videoSrc;
      if(source){this.video=document.createElement('video');Object.assign(this.video,{src:source,muted:true,loop:true,playsInline:true,preload:'none'});this.videoTexture=new THREE.VideoTexture(this.video);this.videoTexture.colorSpace=THREE.SRGBColorSpace;screenMat.map=this.videoTexture;this.video.play().then(()=>{if(!this.disposed)renderer.setAnimationLoop(this.renderOnce);}).catch(()=>{screenMat.map=this.texture;this.renderOnce();});}
    } catch (error) { this.cleanup(); console.info('ShortFlow : aperçu statique activé.'); }
  }
  cleanup() {
    this.disposed=true;this.observer?.disconnect();this.resize?.disconnect();
    this.tween?.scrollTrigger?.kill();this.tween?.kill();
    window.removeEventListener('pointermove',this.onPointer);
    this.canvasTarget.removeEventListener('webglcontextlost',this.onContextLost);
    this.renderer?.setAnimationLoop(null);
    this.video?.pause();if(this.video){this.video.removeAttribute('src');this.video.load();}
    this.videoTexture?.dispose();this.texture?.dispose();this.resources?.forEach(r=>r.dispose());this.renderer?.dispose();
    this.stageTarget.classList.remove('ready');
  }
  disconnect(){this.cleanup();this.motion?.removeEventListener('change',this.onMotion);}
}
