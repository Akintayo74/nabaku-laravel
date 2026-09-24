// Uses only the original Node source and installed dependencies; never reads its .env.
// Captures a small executable baseline. Known broken routes remain documented separately.
const path = require('path');
const fs = require('fs');
const Module = require('module');
const source = path.resolve(process.argv[2] || '../fundraiser-api');
const uri = process.env.BASELINE_MONGODB_URI || 'mongodb://127.0.0.1:27028/nabaku_baseline_test?replicaSet=nabaku-test';
const parsed = new URL(uri);
if (!['127.0.0.1','localhost'].includes(parsed.hostname) || !parsed.pathname.endsWith('_test')) throw new Error('Baseline requires a localhost database ending in _test');
process.env.MONGO_URL = uri;
process.env.SECRET = 'nabaku-test-signing-secret-at-least-32-characters';
process.env.PORT = '29002';
const originalLoad = Module._load;
Module._load = function(request, parent, isMain) {
  if(request === 'dotenv') return {config: () => ({parsed: {}})};
  // Imported but unused by authentication.store.js; missing from package.json.
  if(request === 'shortid') return {};
  if(request === 'axios') return new Proxy({}, {get: () => () => {throw new Error('Outbound provider calls are disabled in baseline capture');}});
  if(request === 'nodemailer') return {createTransport: () => ({sendMail: async () => ({})})};
  return originalLoad.apply(this, arguments);
};
const mongoose = require(path.join(source,'node_modules/mongoose'));
const output = [];
require(path.join(source,'server.js'));
(async () => {
  if(mongoose.connection.readyState !== 1) await new Promise((resolve,reject) => {mongoose.connection.once('open',resolve); mongoose.connection.once('error',reject);});
  const Org = require(path.join(source,'app/models/organization.model'));
  const Project = require(path.join(source,'app/models/project.model'));
  const Wallet = require(path.join(source,'app/models/wallet.model'));
  const Activity = require(path.join(source,'app/models/activity.model'));
  for(const model of [Org,Project,Wallet,Activity]) await model.deleteMany({});
  const org = await Org.create({_id:'507f1f77bcf86cd799439012',name:'Baseline Organization',email:'baseline@example.test',phone:'08099999111',isActive:true,password:require(path.join(source,'node_modules/bcryptjs')).hashSync('password123',10)});
  await Wallet.create({organization:org._id});
  await Project.create({_id:'507f1f77bcf86cd799439013',organization:org._id,title:'Baseline Project',description:'Fixture project',target_amount:10000});
  await Activity.create({_id:'507f1f77bcf86cd799439014',organization:org._id,name:'Baseline Meeting',start:'2026-10-01',end:'2026-10-02'});
  const token = require(path.join(source,'node_modules/jsonwebtoken')).sign({id:org.id,email:org.email},process.env.SECRET,{expiresIn:3600});
  for(const [method,url,auth,body] of [
    ['GET','/',false],['GET','/api/v1/authentication/states',false],
    ['POST','/api/v1/authentication/login',false,{id:org.email,password:'password123'}],
    ['GET','/api/v1/authentication/get-profile',true],['GET','/api/v1/project/random',false],
    ['GET','/api/v1/project/507f1f77bcf86cd799439013',false],['GET','/api/v1/activity/public',false],
    ['GET','/api/v1/wallet',true],['GET','/api/v1/donation',true],
    ['GET','/api/v1/withdraw/all',true],['GET','/api/v1/organization/overview',true],
    ['GET','/api/v1/global/search',false],['GET','/api/v1/project/not-an-id',false],
  ]) {
    const res = await fetch('http://127.0.0.1:29002'+url,{method,headers:{'Content-Type':'application/json',...(auth?{Authorization:'Bearer '+token}:{})},body:body?JSON.stringify(body):undefined});
    const value = await res.json();
    if(value.authorization) value.authorization.token = '<dynamic-token>';
    output.push({method,url,status:res.status,body:value});
  }
  fs.mkdirSync('tests/fixtures',{recursive:true});
  fs.writeFileSync('tests/fixtures/node-baseline.json',JSON.stringify(output,null,2)+'\n');
  await mongoose.disconnect(); console.log('Captured '+output.length+' baseline responses.'); process.exit(0);
})().catch(e=>{console.error(e.message); process.exit(1);});
