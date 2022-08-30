DigiComp.FlowSessionLock
------------------------

![Build status](https://ci.digital-competence.de/api/badges/Packages/DigiComp.FlowSessionLock/status.svg)

By default, the session established by Flow is not "protected" in any way. This package restricts every request to load
the session only, if there are no other requests having it in access currently. It allows to set custom pointcut which 
will set the session in "ReadOnly" mode, which allows concurrent requests to read, but disallows the current request to 
write the session.

If you want to allow concurrent access somewhere, you can add your trigger pointcut in `Settings.yaml` like such:

```yaml
DigiComp:
  FlowSessionLock:
    readOnlyExpressions:
      MyLock: "method(My\\Package\\Controller\\MyController->myAction())"
```
